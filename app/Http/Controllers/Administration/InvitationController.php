<?php

namespace App\Http\Controllers\Administration;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Enterprise;
use App\Models\MfiInstitution;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function index()
    {
        return Inertia::render('Administration/Invitations', ['invitations' => UserInvitation::query()->latest()->limit(100)->get(),
            'roles' => collect(UserRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
            'enterprises' => Enterprise::query()->orderBy('business_name')->get(['id', 'business_name']),
            'institutions' => MfiInstitution::query()->orderBy('name_ar')->get(['id', 'name_ar'])]);
    }

    public function store(Request $request, RecordAuditLog $audit)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), function ($attribute, $value, $fail) {
            if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($value)])->exists()) {
                $fail('هذا البريد مستخدم بالفعل.');
            }
        }],
            'role' => ['required', Rule::enum(UserRole::class)], 'enterprise_id' => ['nullable', 'required_if:role,sme_user', 'exists:enterprises,id'],
            'mfi_institution_id' => ['nullable', 'required_if:role,mfi_user', 'exists:mfi_institutions,id']]);
        $token = Str::random(64);
        $invitation = UserInvitation::query()->updateOrCreate(['email' => $data['email']], $data + ['enterprise_id' => null, 'mfi_institution_id' => null, 'token_hash' => hash('sha256', $token), 'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7), 'accepted_at' => null, 'revoked_at' => null]);
        $audit->record($request->user(), 'user.invited', $invitation, ['email' => $data['email'], 'role' => $data['role'], 'enterprise_id' => $data['enterprise_id'] ?? null]);

        // Show once to the administrator for delivery using their approved channel.
        return back()->with('invitation_link', route('invitations.accept', $token))->with('success', 'تم إنشاء دعوة صالحة لسبعة أيام. انسخ الرابط وشاركه مع صاحب البريد المحدد.');
    }

    public function revoke(Request $request, UserInvitation $invitation, RecordAuditLog $audit)
    {
        abort_if($invitation->accepted_at !== null, 409);
        $invitation->update(['revoked_at' => now()]);
        $audit->record($request->user(), 'user.invitation_revoked', $invitation);

        return back()->with('success', 'تم إلغاء الدعوة.');
    }

    private function valid(string $token): UserInvitation
    {
        $invite = UserInvitation::query()->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_if($invite->accepted_at || $invite->revoked_at || $invite->expires_at->isPast(), 410, 'انتهت صلاحية الدعوة.');
        $issuer = User::query()->find($invite->invited_by);
        abort_unless($issuer?->isActive() && $issuer->role === UserRole::BeaconAdmin, 410);

        return $invite;
    }

    public function accept(string $token)
    {
        $invite = $this->valid($token);

        return Inertia::render('Auth/AcceptInvitation', ['invitation' => ['name' => $invite->name, 'email' => $invite->email], 'token' => $token]);
    }

    public function register(Request $request, string $token, RecordAuditLog $audit)
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(12)]]);
        DB::transaction(function () use ($token, $data, $audit) {
            $invite = $this->valid($token);
            $invite = UserInvitation::query()->lockForUpdate()->findOrFail($invite->id);
            abort_if($invite->accepted_at || $invite->revoked_at || $invite->expires_at->isPast() || ! hash_equals($invite->token_hash, hash('sha256', $token)), 410);
            $issuer = User::query()->lockForUpdate()->find($invite->invited_by);
            abort_unless($issuer?->isActive() && $issuer->role === UserRole::BeaconAdmin, 410);
            if (User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($invite->email)])->exists()) {
                throw ValidationException::withMessages(['password' => 'يوجد حساب بهذا البريد. اطلب من المسؤول مراجعة الدعوة.']);
            }
            $user = new User(['name' => $invite->name, 'email' => $invite->email, 'password' => $data['password']]);
            $user->forceFill(['role' => $invite->role, 'is_active' => true, 'mfi_institution_id' => $invite->role === 'mfi_user' ? $invite->mfi_institution_id : null])->save();
            if ($invite->enterprise_id && $invite->role === 'sme_user') {
                Enterprise::query()->findOrFail($invite->enterprise_id)->members()->attach($user->id);
            }
            if ($invite->enterprise_id && $invite->role === 'beacon_advisor') {
                Enterprise::query()->findOrFail($invite->enterprise_id)->advisors()->attach($user->id);
            }
            $invite->update(['accepted_at' => now()]);
            $audit->record($user, 'user.invitation_accepted', $invite, ['user_id' => $user->id]);
        });

        return redirect()->route('login')->with('success', 'تم إنشاء الحساب. يمكنك تسجيل الدخول.');
    }
}
