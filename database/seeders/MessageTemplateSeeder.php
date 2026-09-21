<?php

namespace Database\Seeders;

use App\Actions\MessageTemplates\GenerateDefaultMessageTemplate;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $generateDefaultMessageTemplate = new GenerateDefaultMessageTemplate;

        Organization::query()->eachById(function (Organization $organization) use ($generateDefaultMessageTemplate): void {
            $generateDefaultMessageTemplate->handle($organization);
        });
    }
}
