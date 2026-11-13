<?php

namespace App\Http\Controllers\Administration;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\BaselineProfileSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\UpdateBaselineFieldVisibilityRequest;
use App\Models\BaselineProfileField;
use App\Services\Baseline\BaselineProfilePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BaselineFieldController extends Controller
{
    public function index(BaselineProfilePresenter $presenter): Response
    {
        return Inertia::render('Administration/BaselineFields/Index', [
            'sections' => $presenter->adminCatalog(),
            'section_labels' => collect(BaselineProfileSection::cases())
                ->map(fn (BaselineProfileSection $s) => ['value' => $s->value, 'label' => $s->label()])
                ->values()
                ->all(),
        ]);
    }

    public function updateVisibility(
        UpdateBaselineFieldVisibilityRequest $request,
        RecordAuditLog $audit,
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            if (isset($validated['section'], $validated['section_visible'])) {
                BaselineProfileField::query()
                    ->where('section', $validated['section'])
                    ->where('is_child', false)
                    ->update(['is_visible' => (bool) $validated['section_visible']]);
            }

            foreach ($validated['fields'] ?? [] as $row) {
                BaselineProfileField::query()
                    ->whereKey($row['id'])
                    ->update(['is_visible' => (bool) $row['is_visible']]);
            }
        });

        $audit->record($request->user(), 'baseline.fields.visibility_updated', $request->user(), [
            'section' => $validated['section'] ?? null,
            'field_count' => count($validated['fields'] ?? []),
        ]);

        return back()->with('success', 'تم تحديث إعدادات عرض حقول البروفايل.');
    }

    public function importForm(): Response
    {
        return Inertia::render('Administration/BaselineFields/Import');
    }

    public function import(
        Request $request,
        \App\Actions\Baseline\ImportBaselineXlsxAction $action,
    ): RedirectResponse {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream', 'max:20480'],
        ], [
            'file.required' => 'اختر ملف Excel.',
            'file.mimetypes' => 'الملف يجب أن يكون بصيغة xlsx.',
        ]);

        $uploaded = $request->file('file');
        $tmp = $uploaded->getRealPath();
        $result = $action->execute($request->user(), $tmp);

        $message = 'تم الاستيراد: '.
            $result['created'].' جديد، '.
            $result['updated'].' محدّث، '.
            $result['skipped'].' متجاهل.';

        if ($result['errors'] !== []) {
            return back()->with('success', $message)->withErrors(['file' => implode(' | ', array_slice($result['errors'], 0, 5))]);
        }

        return back()->with('success', $message);
    }
}
