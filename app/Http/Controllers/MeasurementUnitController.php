<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeasurementUnitRequest;
use App\Http\Requests\UpdateMeasurementUnitRequest;
use App\Models\MeasurementUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeasurementUnitController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();

        return view('catalog.units.index', [
            'units' => MeasurementUnit::query()
                ->withCount('products')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($builder) use ($search): void {
                        $builder->where('name', 'like', "%{$search}%")
                            ->orWhere('symbol', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('catalog.units.form', ['measurementUnit' => new MeasurementUnit]);
    }

    public function store(StoreMeasurementUnitRequest $request): RedirectResponse
    {
        MeasurementUnit::query()->create($request->validated());

        return redirect()->route('measurement-units.index')->with('success', 'Unidad de medida creada correctamente.');
    }

    public function edit(MeasurementUnit $measurementUnit): View
    {
        return view('catalog.units.form', compact('measurementUnit'));
    }

    public function update(UpdateMeasurementUnitRequest $request, MeasurementUnit $measurementUnit): RedirectResponse
    {
        $measurementUnit->update($request->validated());

        return redirect()->route('measurement-units.index')->with('success', 'Unidad de medida actualizada correctamente.');
    }

    public function destroy(MeasurementUnit $measurementUnit): RedirectResponse
    {
        if ($measurementUnit->products()->exists()) {
            return back()->with('error', 'No se puede eliminar una unidad usada por productos.');
        }

        $measurementUnit->delete();

        return back()->with('success', 'Unidad de medida eliminada correctamente.');
    }
}
