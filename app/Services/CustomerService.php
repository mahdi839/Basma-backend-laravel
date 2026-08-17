<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerBadge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function upsertFromOrder(?string $name, ?string $phone): ?Customer
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $customer = Customer::firstOrNew(['phone' => $phone]);
        $customer->name = $name ?: ($customer->name ?: 'Unknown');
        $customer->save();

        return $customer;
    }

    public function assignBadge(Customer $customer, ?string $badgeTitle): void
    {
        if (!$badgeTitle) {
            $customer->badge()->delete();

            return;
        }

        if (!array_key_exists($badgeTitle, CustomerBadge::TITLES)) {
            throw ValidationException::withMessages([
                'badge_title' => 'Invalid badge selected.',
            ]);
        }

        $customer->badge()->updateOrCreate(
            ['customer_id' => $customer->id],
            ['badge_title' => $badgeTitle]
        );
    }

    public function syncUserAccount(Customer $customer, ?string $email, ?string $password): void
    {
        $email = $email ? strtolower(trim($email)) : null;

        if (!$email && !$password) {
            return;
        }

        if ($email && $password) {
            $user = $customer->user;

            if (!$user && $customer->user_id) {
                $user = User::find($customer->user_id);
            }

            if (!$user) {
                $existingUser = User::where('email', $email)->first();
                if ($existingUser) {
                    throw ValidationException::withMessages([
                        'email' => 'This email is already used by another user.',
                    ]);
                }

                $user = User::create([
                    'name' => $customer->name,
                    'email' => $email,
                    'password' => Hash::make($password),
                ]);
                $user->assignRole('user');
            } else {
                $emailTaken = User::where('email', $email)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($emailTaken) {
                    throw ValidationException::withMessages([
                        'email' => 'This email is already used by another user.',
                    ]);
                }

                $user->update([
                    'name' => $customer->name,
                    'email' => $email,
                    'password' => Hash::make($password),
                ]);
            }

            $customer->user_id = $user->id;
            $customer->save();

            return;
        }

        if ($email && $customer->user) {
            $emailTaken = User::where('email', $email)
                ->where('id', '!=', $customer->user->id)
                ->exists();

            if ($emailTaken) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already used by another user.',
                ]);
            }

            $customer->user->update([
                'name' => $customer->name,
                'email' => $email,
            ]);
        }
    }
}
