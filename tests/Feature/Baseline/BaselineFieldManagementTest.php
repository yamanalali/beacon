<?php

namespace Tests\Feature\Baseline;

use App\Enums\UserRole;
use App\Models\BaselineProfileField;
use App\Models\Enterprise;
use App\Models\EnterpriseBaselineProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaselineFieldManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::BeaconAdmin]);
    }

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'key' => 'extra_notes',
            'label_ar' => 'ملاحظات إضافية',
            'section' => 'other',
            'input_kind' => 'text',
        ];
    }

    public function test_admin_can_create_a_visible_non_system_field_with_audit(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.baseline-fields.store'), $this->payload(['excel_column' => 'zz']))
            ->assertRedirect()
            ->assertSessionHas('success', ui('flash.baseline_field_saved'));

        $field = BaselineProfileField::query()->where('key', 'extra_notes')->firstOrFail();
        $this->assertTrue($field->is_visible);
        $this->assertFalse($field->is_system);
        $this->assertFalse($field->is_child);
        $this->assertSame('ZZ', $field->excel_column);
        $this->assertSame('other', $field->section->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'baseline.field.created', 'actor_id' => $admin->id]);
    }

    public function test_create_validates_key_label_section_kind_and_uniqueness(): void
    {
        $admin = $this->admin();
        BaselineProfileField::query()->create([
            'key' => 'taken_key', 'label_ar' => 'قائم', 'section' => 'other', 'input_kind' => 'text',
            'excel_column' => 'QQ', 'sort_order' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.baseline-fields.store'), [])
            ->assertSessionHasErrors(['key', 'label_ar', 'section', 'input_kind']);

        $this->post(route('admin.baseline-fields.store'), $this->payload(['key' => 'Bad Key!']))
            ->assertSessionHasErrors('key');
        $this->post(route('admin.baseline-fields.store'), $this->payload(['key' => 'taken_key']))
            ->assertSessionHasErrors('key');
        $this->post(route('admin.baseline-fields.store'), $this->payload(['section' => 'nope']))
            ->assertSessionHasErrors('section');
        $this->post(route('admin.baseline-fields.store'), $this->payload(['input_kind' => 'script']))
            ->assertSessionHasErrors('input_kind');
        $this->post(route('admin.baseline-fields.store'), $this->payload(['excel_column' => 'QQ']))
            ->assertSessionHasErrors('excel_column');

        $this->assertDatabaseCount('baseline_profile_fields', 1);
    }

    public function test_payload_cannot_create_system_or_child_field(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.baseline-fields.store'), $this->payload(['is_system' => true, 'is_child' => true, 'sort_order' => 999]))
            ->assertRedirect();

        $field = BaselineProfileField::query()->where('key', 'extra_notes')->firstOrFail();
        $this->assertFalse($field->is_system);
        $this->assertFalse($field->is_child);
        $this->assertLessThan(999, $field->sort_order);
    }

    public function test_hide_is_a_logical_delete_that_keeps_the_field_and_answers(): void
    {
        $admin = $this->admin();
        $field = BaselineProfileField::query()->create([
            'key' => 'hide_me', 'label_ar' => 'للإخفاء', 'section' => 'other', 'input_kind' => 'text', 'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.baseline-fields.hide', $field))
            ->assertRedirect()
            ->assertSessionHas('success', ui('flash.baseline_field_hidden'));

        $this->assertDatabaseHas('baseline_profile_fields', ['id' => $field->id, 'is_visible' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'baseline.field.hidden', 'actor_id' => $admin->id]);
    }

    public function test_unused_non_system_field_can_be_deleted(): void
    {
        $admin = $this->admin();
        $field = BaselineProfileField::query()->create([
            'key' => 'unused_field', 'label_ar' => 'غير مستخدم', 'section' => 'other', 'input_kind' => 'text', 'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.baseline-fields.destroy', $field))
            ->assertRedirect()
            ->assertSessionHas('success', ui('flash.baseline_field_deleted'));

        $this->assertDatabaseMissing('baseline_profile_fields', ['id' => $field->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'baseline.field.deleted', 'actor_id' => $admin->id]);
    }

    public function test_field_with_saved_answers_cannot_be_deleted(): void
    {
        $field = BaselineProfileField::query()->create([
            'key' => 'used_field', 'label_ar' => 'مستخدم', 'section' => 'other', 'input_kind' => 'text', 'sort_order' => 1,
        ]);
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        EnterpriseBaselineProfile::query()->create([
            'enterprise_id' => $enterprise->id,
            'answers' => ['used_field' => 'قيمة'],
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.baseline-fields.destroy', $field))
            ->assertSessionHasErrors('field');

        $this->assertDatabaseHas('baseline_profile_fields', ['id' => $field->id]);
    }

    public function test_system_field_cannot_be_deleted(): void
    {
        $field = BaselineProfileField::query()->create([
            'key' => '_uuid', 'label_ar' => 'نظامي', 'section' => 'ops', 'input_kind' => 'text',
            'is_system' => true, 'sort_order' => 1,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.baseline-fields.destroy', $field))
            ->assertSessionHasErrors('field');

        $this->assertDatabaseHas('baseline_profile_fields', ['id' => $field->id]);
    }

    public function test_non_admin_roles_are_forbidden(): void
    {
        $field = BaselineProfileField::query()->create([
            'key' => 'guarded', 'label_ar' => 'محمي', 'section' => 'other', 'input_kind' => 'text', 'sort_order' => 1,
        ]);

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->post(route('admin.baseline-fields.store'), $this->payload())->assertForbidden();
            $this->actingAs($actor)->post(route('admin.baseline-fields.hide', $field))->assertForbidden();
            $this->actingAs($actor)->delete(route('admin.baseline-fields.destroy', $field))->assertForbidden();
        }

        $this->assertDatabaseHas('baseline_profile_fields', ['id' => $field->id, 'is_visible' => true]);
        $this->assertDatabaseMissing('baseline_profile_fields', ['key' => 'extra_notes']);
    }

    public function test_guest_is_redirected(): void
    {
        $this->post(route('admin.baseline-fields.store'), $this->payload())->assertRedirect(route('login'));
    }
}
