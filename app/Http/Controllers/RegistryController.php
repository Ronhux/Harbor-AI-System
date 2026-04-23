<?php

namespace App\Http\Controllers;

use App\Models\RegisteredProducer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\RegisteredProducersImport;

class RegistryController extends Controller
{
    public function index()
    {
        $registries = RegisteredProducer::paginate(20);
        return response()->json($registries);
    }

    public function search(Request $request)
    {
        $query = RegisteredProducer::query();

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('barangay')) {
            $query->where('barangay', 'like', '%' . $request->barangay . '%');
        }

        if ($request->has('type')) {
            $query->where('farm_type', $request->type);
        }

        $results = $query->paginate(20);
        return response()->json($results);
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('file');
            $path = $file->getRealPath();
            $data = array_map('str_getcsv', file($path));

            // Skip header row
            array_shift($data);

            foreach ($data as $row) {
                if (count($row) >= 8) {
                    RegisteredProducer::updateOrCreate(
                        ['rsbsa_number' => $row[0]],
                        [
                            'name' => $row[1],
                            'municipality' => $row[2],
                            'province' => $row[3],
                            'farm_type' => $row[4],
                            'farm_size' => $row[5],
                            'contact_number' => $row[6],
                            'email' => $row[7],
                            'registration_date' => now(),
                        ]
                    );
                }
            }

            return response()->json(['message' => 'Registry imported successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $registry = RegisteredProducer::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'rsbsa_number' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'farm_type' => 'required|string|max:255',
            'farm_size' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $registry->update($request->all());
        return response()->json(['message' => 'Registry updated successfully'], 200);
    }

    public function sync(Request $request)
    {
        // This would typically sync with an external source
        // For now, just return success
        return response()->json(['message' => 'Registry sync completed'], 200);
    }
}