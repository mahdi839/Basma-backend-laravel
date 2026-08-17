<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerBadge;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customerService)
    {
    }

    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $badge = $request->input('badge', '');

        $customers = Customer::with('badge')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                });
            })
            ->when($badge, function ($query) use ($badge) {
                $query->whereHas('badge', function ($q) use ($badge) {
                    $q->where('badge_title', $badge);
                });
            })
            ->latest()
            ->paginate(20);

        $customers->getCollection()->transform(function (Customer $customer) {
            return $this->formatCustomer($customer);
        });

        return response()->json($customers);
    }

    public function show($id)
    {
        $customer = Customer::with('badge')->findOrFail($id);

        return response()->json([
            'data' => $this->formatCustomer($customer),
            'badge_options' => $this->badgeOptionsList(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::with('badge')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($customer->id)],
            'email' => ['nullable', 'email', Rule::unique('customers', 'email')->ignore($customer->id)],
            'password' => 'nullable|string|min:6',
            'badge_title' => ['nullable', 'string', Rule::in(array_keys(CustomerBadge::TITLES))],
        ]);

        $customer->name = $validated['name'];
        $customer->phone = trim($validated['phone']);
        $customer->email = !empty($validated['email']) ? strtolower(trim($validated['email'])) : null;

        if (!empty($validated['password'])) {
            $customer->password = Hash::make($validated['password']);
        }

        $customer->save();

        $this->customerService->assignBadge($customer, $validated['badge_title'] ?? null);
        $this->customerService->syncUserAccount(
            $customer,
            $customer->email,
            $validated['password'] ?? null
        );

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $this->formatCustomer($customer->fresh('badge')),
        ]);
    }

    public function assignBadge(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'name' => 'nullable|string|max:255',
            'badge_title' => ['nullable', 'string', Rule::in(array_keys(CustomerBadge::TITLES))],
        ]);

        $customer = $this->customerService->upsertFromOrder(
            $validated['name'] ?? null,
            $validated['phone']
        );

        if (!$customer) {
            return response()->json([
                'message' => 'A valid phone number is required to assign a badge.',
            ], 422);
        }

        $this->customerService->assignBadge($customer, $validated['badge_title'] ?? null);

        return response()->json([
            'message' => 'Badge saved successfully',
            'data' => $this->formatCustomer($customer->fresh('badge')),
        ]);
    }

    public function badgeOptions()
    {
        return response()->json([
            'data' => $this->badgeOptionsList(),
        ]);
    }

    private function formatCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'user_id' => $customer->user_id,
            'has_user_account' => (bool) $customer->user_id,
            'assigned_badge' => CustomerBadge::payload($customer->badge?->badge_title),
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];
    }

    private function badgeOptionsList(): array
    {
        return collect(CustomerBadge::TITLES)
            ->map(fn ($label, $title) => [
                'title' => $title,
                'label' => $label,
            ])
            ->values()
            ->all();
    }
}
