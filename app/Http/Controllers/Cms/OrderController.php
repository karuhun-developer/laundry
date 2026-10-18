<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Orders\DeleteOrder;
use App\Actions\Orders\DownloadOrderImportTemplateCsv;
use App\Actions\Orders\ExportOrdersCsv;
use App\Actions\Orders\FilterOrderQueue;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\DeleteOrderRequest;
use App\Http\Requests\Cms\ImportCsvRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\ProcessOrderImportJob;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request, FilterOrderQueue $filterOrders): Response
    {
        Gate::authorize('viewAny', Order::class);
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'branch_id' => ['sometimes', 'nullable', 'integer'],
            'organization_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', Rule::enum(OrderStatus::class)],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
            'unpaid' => ['sometimes', 'boolean'],
            'overdue' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $isSuperadmin = $user->hasRole(RoleName::Superadmin->value);
        $isBranchAdmin = $user->hasRole(RoleName::BranchAdmin->value);
        $branchIds = $isBranchAdmin ? $user->branches()->pluck('branches.id') : null;

        $query = Order::query()
            ->with([
                'organization:id,name,slug',
                'branch:id,organization_id,name,code,qris_enabled',
                'items' => fn ($query) => $query->orderBy('id'),
                'payments' => fn ($query) => $query->with('statusEvents')->orderBy('id'),
                'statusEvents' => fn ($query) => $query->orderBy('id'),
                'tasks.events', 'tasks.media', 'complaints.events', 'complaints.media',
            ])
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when($isBranchAdmin, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->latest('id');
        $filterOrders->handle($query, $filters);
        $statistics = $filterOrders->statistics($query);
        $orders = $query->paginate(25)->withQueryString();

        $branches = Branch::query()
            ->with('organization:id,name')
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when($isBranchAdmin, fn ($query) => $query->whereIn('id', $branchIds))
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'code', 'is_active']);

        $organizations = $isSuperadmin
            ? Organization::query()->orderBy('name')->get(['id', 'name'])
            : [];

        $statuses = collect(OrderStatus::cases())->map(fn (OrderStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);

        return Inertia::render('cms/Orders', [
            'orders' => OrderResource::collection($orders->getCollection())->resolve(),
            'pagination' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
            'filters' => $filters,
            'statistics' => $statistics,
            'branches' => $branches,
            'organizations' => $organizations,
            'orderStatuses' => $statuses,
        ]);
    }

    public function export(
        Request $request,
        FilterOrderQueue $filterOrders,
        ExportOrdersCsv $exportOrders,
    ): StreamedResponse {
        Gate::authorize('viewAny', Order::class);

        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'branch_id' => ['sometimes', 'nullable', 'integer'],
            'organization_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', Rule::enum(OrderStatus::class)],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
            'unpaid' => ['sometimes', 'boolean'],
            'overdue' => ['sometimes', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $isSuperadmin = $user->hasRole(RoleName::Superadmin->value);
        $isBranchAdmin = $user->hasRole(RoleName::BranchAdmin->value);
        $branchIds = $isBranchAdmin ? $user->branches()->pluck('branches.id') : null;

        $query = Order::query()
            ->when(! $isSuperadmin, fn ($query) => $query->where('organization_id', $user->organization_id))
            ->when($isBranchAdmin, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->latest('id');

        $filterOrders->handle($query, $filters);

        return $exportOrders->handle($query);
    }

    public function template(DownloadOrderImportTemplateCsv $downloadTemplate): StreamedResponse
    {
        Gate::authorize('create', Order::class);

        return $downloadTemplate->handle();
    }

    public function import(
        ImportCsvRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('create', Order::class);

        /** @var User $user */
        $user = $request->user();
        $file = $request->file('file');
        \assert($file instanceof UploadedFile);

        $importId = (string) Str::uuid();
        $fileName = "{$importId}.csv";
        $storedPath = $file->storeAs('imports/orders', $fileName);
        $fullPath = Storage::path($storedPath);
        $totalRows = $this->countCsvRows($fullPath);

        Cache::put("order_import:{$importId}", [
            'id' => $importId,
            'status' => 'pending',
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'imported' => 0,
            'errors' => [],
            'error_count' => 0,
        ], now()->addDay());

        ProcessOrderImportJob::dispatch($importId, $storedPath, $user->id, $totalRows);

        if ($request->expectsJson()) {
            return response()->json([
                'import_id' => $importId,
                'status' => 'pending',
                'total_rows' => $totalRows,
            ]);
        }

        $cached = Cache::get("order_import:{$importId}");
        $imported = (int) ($cached['imported'] ?? $totalRows);
        $errors = (array) ($cached['errors'] ?? []);

        if (! empty($errors)) {
            $msg = sprintf(
                'Berhasil mengimpor %d pesanan. Terdapat %d kendala: %s',
                $imported,
                count($errors),
                implode('; ', array_slice($errors, 0, 3)),
            );

            return back()->with($imported > 0 ? 'warning' : 'error', $msg);
        }

        return back()->with('success', sprintf('Berhasil mengimpor %d pesanan.', $imported));
    }

    public function importStatus(string $importId): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $status = Cache::get("order_import:{$importId}");

        if ($status === null) {
            return response()->json(['error' => 'Data status impor tidak ditemukan.'], 404);
        }

        return response()->json($status);
    }

    private function countCsvRows(string $filePath): int
    {
        $count = 0;
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return 0;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (! empty(array_filter($row, fn ($val): bool => trim((string) $val) !== ''))) {
                $count++;
            }
        }

        fclose($handle);

        return $count;
    }

    public function destroy(
        DeleteOrderRequest $request,
        Order $order,
        DeleteOrder $deleteOrder,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $deleteOrder->handle($order, $user);

        return back()->with('success', 'Pesanan berhasil dihapus.');
    }
}
