<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SeniorManager;
use App\Models\SystemCurrency;
use App\Models\StandardTerm;
use Illuminate\Http\Request;

class SettingController extends Controller
{

    public function index()
    {
        $settings = Setting::all()->groupBy('group');
        $managers = SeniorManager::all();
        $terms = StandardTerm::all();
        $currencies = SystemCurrency::all();

        return view('settings.index', compact('settings', 'managers', 'terms', 'currencies'));
    }

    public function updateGeneral(Request $request)
    {
        $data = $request->except('_token');

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()->route('settings.index')->with('success', 'General settings updated successfully.');
    }

    public function updateTax(Request $request)
    {
        $request->validate([
            'sscl_rate' => 'required|numeric|min:0',
            'vat_rate' => 'required|numeric|min:0',
        ]);

        Setting::set('sscl_rate', $request->sscl_rate, 'tax');
        Setting::set('vat_rate', $request->vat_rate, 'tax');

        return redirect()->route('settings.index')->with('success', 'Tax settings updated successfully.');
    }

    public function storeManager(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
        ]);

        SeniorManager::create($request->only('name', 'designation'));

        return redirect()->route('settings.index')->with('success', 'Senior manager added successfully.');
    }

    public function destroyManager(SeniorManager $manager)
    {
        try {
            $manager->delete();
            return redirect()->route('settings.index')->with('success', 'Senior manager removed successfully.');
        } catch (\Exception $e) {
            return redirect()->route('settings.index')->with('error', 'Cannot delete manager. It may be in use.');
        }
    }

    public function updateManager(Request $request, SeniorManager $manager)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
        ]);

        $manager->update($request->only('name', 'designation'));

        return redirect()->route('settings.index')->with('success', 'Senior manager updated successfully.');
    }

    public function storeTerm(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        StandardTerm::create($request->only('content'));

        return redirect()->route('settings.index')->with('success', 'Standard term added successfully.');
    }

    public function destroyTerm(StandardTerm $term)
    {
        try {
            $term->delete();
            return redirect()->route('settings.index')->with('success', 'Standard term removed successfully.');
        } catch (\Exception $e) {
            return redirect()->route('settings.index')->with('error', 'Cannot delete term. It may be in use.');
        }
    }

    public function updateTerm(Request $request, StandardTerm $term)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $term->update($request->only('content'));

        return redirect()->route('settings.index')->with('success', 'Standard term updated successfully.');
    }
    public function storeCurrency(Request $request)
    {
        // Role Check (Super Admin only)
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'code' => 'required|string|max:3|unique:system_currencies,code',
            'name' => 'nullable|string|max:255',
            'symbol' => 'nullable|string|max:10',
        ]);

        \App\Models\SystemCurrency::create($request->only('code', 'name', 'symbol'));

        return redirect()->route('settings.index')->with('success', 'Currency added successfully.');
    }

    public function destroyCurrency(\App\Models\SystemCurrency $currency)
    {
        // Debugging
        // dd('Arrived at destroyCurrency', $currency);

        // Role Check (Super Admin only)
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $currency->delete();
            return redirect()->route('settings.index')->with('success', 'Currency removed successfully.');
        } catch (\Exception $e) {
            return redirect()->route('settings.index')->with('error', 'Cannot delete currency. It may be in use.');
        }
    }

    public function updateCurrency(Request $request, \App\Models\SystemCurrency $currency)
    {
        // Role Check (Super Admin only)
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'code' => 'required|string|max:3|unique:system_currencies,code,' . $currency->id,
            'name' => 'nullable|string|max:255',
            'symbol' => 'nullable|string|max:10',
        ]);

        $currency->update($request->only('code', 'name', 'symbol'));

        return redirect()->route('settings.index')->with('success', 'Currency updated successfully.');
    }
}
