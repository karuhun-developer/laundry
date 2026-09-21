<?php

namespace App\Http\Controllers\Cms;

use App\Actions\Cashflow\CreateCashflowCategory;
use App\Actions\Cashflow\DeleteCashflowCategory;
use App\Actions\Cashflow\UpdateCashflowCategory;
use App\Data\CashflowCategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\DeleteCashflowCategoryRequest;
use App\Http\Requests\Cms\StoreCashflowCategoryRequest;
use App\Http\Requests\Cms\UpdateCashflowCategoryRequest;
use App\Models\CashflowCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CashflowCategoryController extends Controller
{
    public function store(
        StoreCashflowCategoryRequest $request,
        CreateCashflowCategory $createCashflowCategory,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $createCashflowCategory->handle(CashflowCategoryData::fromStoreRequest($request, $user));

        return back()->with('success', 'Kategori cashflow berhasil dibuat.');
    }

    public function update(
        UpdateCashflowCategoryRequest $request,
        CashflowCategory $cashflowCategory,
        UpdateCashflowCategory $updateCashflowCategory,
    ): RedirectResponse {
        $updateCashflowCategory->handle(
            $cashflowCategory,
            CashflowCategoryData::fromUpdateRequest($request, $cashflowCategory),
        );

        return back()->with('success', 'Kategori cashflow berhasil diperbarui.');
    }

    public function destroy(
        DeleteCashflowCategoryRequest $request,
        CashflowCategory $cashflowCategory,
        DeleteCashflowCategory $deleteCashflowCategory,
    ): RedirectResponse {
        $deleteCashflowCategory->handle($cashflowCategory);

        return back()->with('success', 'Kategori cashflow berhasil dihapus.');
    }
}
