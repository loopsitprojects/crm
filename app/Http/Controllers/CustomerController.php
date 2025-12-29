<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = Customer::all();
        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'telephone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'business_registration_number' => 'nullable|string|max:255',
            'primary_contact_name' => 'nullable|string|max:255',
            'primary_contact_designation' => 'nullable|string|max:255',
            'primary_contact_mobile' => 'nullable|string|max:20',
            'primary_contact_office' => 'nullable|string|max:255',
            'primary_contact_email' => 'nullable|email|max:255',
            'promo_contact_name' => 'nullable|string|max:255',
            'promo_contact_designation' => 'nullable|string|max:255',
            'promo_contact_mobile' => 'nullable|string|max:20',
            'promo_contact_office' => 'nullable|string|max:255',
            'promo_contact_email' => 'nullable|email|max:255',
            'customer_tax_number' => 'nullable|string|max:255',
            'customer_vat_registration_number' => 'nullable|string|max:255',
            'customer_suspended_vat_registration_number' => 'nullable|string|max:255',
            'approved_credit_period' => 'nullable|string|max:255',
            'approved_credit_limit' => 'nullable|numeric|min:0',
        ]);

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
