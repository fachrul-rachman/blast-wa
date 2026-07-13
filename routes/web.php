<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Campaigns\CampaignController;
use App\Http\Controllers\Campaigns\CampaignDuplicateController;
use App\Http\Controllers\Campaigns\CampaignImportController;
use App\Http\Controllers\Campaigns\CampaignImportMappingController;
use App\Http\Controllers\Campaigns\CampaignRetryFailedController;
use App\Http\Controllers\Campaigns\CampaignScheduleController;
use App\Http\Controllers\Campaigns\CampaignSendController;
use App\Http\Controllers\Campaigns\CampaignVariableMappingController;
use App\Http\Controllers\Meta\WhatsAppWebhookController;
use App\Http\Controllers\Templates\TemplateController;
use Illuminate\Support\Facades\Route;

Route::get('webhooks/meta/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('meta.whatsapp.webhook.verify');
Route::post('webhooks/meta/whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('meta.whatsapp.webhook.handle');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::inertia('/', 'dashboard')->name('dashboard');
    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::post('campaigns/{campaign}/import', [CampaignImportController::class, 'store'])->name('campaigns.import.store');
    Route::patch('campaigns/{campaign}/import-mapping', [CampaignImportMappingController::class, 'update'])->name('campaigns.import.mapping.update');
    Route::patch('campaigns/{campaign}/duplicates', [CampaignDuplicateController::class, 'update'])->name('campaigns.duplicates.update');
    Route::patch('campaigns/{campaign}/variable-mappings', [CampaignVariableMappingController::class, 'update'])->name('campaigns.variable-mappings.update');
    Route::post('campaigns/{campaign}/send', [CampaignSendController::class, 'store'])->name('campaigns.send.store');
    Route::post('campaigns/{campaign}/retry-failed', [CampaignRetryFailedController::class, 'store'])->name('campaigns.retry-failed.store');
    Route::post('campaigns/{campaign}/schedule', [CampaignScheduleController::class, 'store'])->name('campaigns.schedule.store');
    Route::patch('campaigns/{campaign}/schedule', [CampaignScheduleController::class, 'update'])->name('campaigns.schedule.update');
    Route::delete('campaigns/{campaign}/schedule', [CampaignScheduleController::class, 'destroy'])->name('campaigns.schedule.destroy');
    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
    Route::post('templates/sync', [TemplateController::class, 'sync'])->name('templates.sync');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
