<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ToolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tools = Tool::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.tools.index', compact('tools'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.tools.create-simple');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tools,slug',
            'description' => 'required|string',
            'short_description' => 'required|string|max:500',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'image' => 'required|url',
            'download_url' => 'required|url',
            'demo_url' => 'nullable|url',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',

            // License fields
            'license_type' => 'required|in:FREE,DEMO,MONTHLY,YEARLY,LIFETIME,CONSIGNMENT',
            'demo_days' => 'nullable|integer|min:1|max:365',
            'monthly_price' => 'nullable|numeric|min:0',
            'yearly_price' => 'nullable|numeric|min:0',
            'max_devices' => 'required|integer|min:1|max:100',
            'allow_transfer' => 'boolean',

            // Ownership fields
            'is_own_tool' => 'boolean',
            'owner_name' => 'nullable|string|max:255',
            'owner_contact' => 'nullable|email|max:255',
            'commission_rate' => 'nullable|numeric|min:0|max:100',

            // Tool metadata
            'version' => 'nullable|string|max:50',
            'system_requirements' => 'nullable|string',
            'features' => 'nullable|string',
        ]);

        // Set boolean defaults
        $validated['is_active'] = $request->has('is_active');
        $validated['is_featured'] = $request->has('is_featured');
        $validated['allow_transfer'] = $request->has('allow_transfer');
        $validated['is_own_tool'] = $request->input('license_type') !== 'CONSIGNMENT';

        // Set sort order
        $validated['sort_order'] = Tool::max('sort_order') + 1;

        // Process features (convert from textarea to array)
        if ($validated['features']) {
            $features = array_filter(array_map('trim', explode("\n", $validated['features'])));
            $validated['features'] = $features;
        } else {
            $validated['features'] = [];
        }

        // Set defaults based on license type
        switch ($validated['license_type']) {
            case 'FREE':
                $validated['price'] = 0;
                $validated['sale_price'] = null;
                break;

            case 'CONSIGNMENT':
                $validated['is_own_tool'] = false;
                if (!$validated['commission_rate']) {
                    $validated['commission_rate'] = 30.00;
                }
                break;

            default:
                $validated['is_own_tool'] = true;
                break;
        }

        // Create tool
        $tool = Tool::create($validated);

        return redirect()
            ->route('admin.tools.index')
            ->with('success', "Tool '{$tool->name}' đã được tạo thành công!");
    }

    /**
     * Display the specified resource.
     */
    public function show(Tool $tool)
    {
        return view('admin.tools.show', compact('tool'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tool $tool)
    {
        return view('admin.tools.edit', compact('tool'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tool $tool)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tools,slug,' . $tool->id,
            'description' => 'required|string',
            'short_description' => 'required|string|max:500',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'image' => 'required|url',
            'download_url' => 'required|url',
            'demo_url' => 'nullable|url',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        // Set defaults
        $validated['is_active'] = $request->has('is_active');
        $validated['is_featured'] = $request->has('is_featured');

        // Update tool
        $tool->update($validated);

        return redirect()
            ->route('admin.tools.index')
            ->with('success', "Tool '{$tool->name}' đã được cập nhật thành công!");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tool $tool)
    {
        $toolName = $tool->name;
        $tool->delete();

        return redirect()
            ->route('admin.tools.index')
            ->with('success', "Tool '{$toolName}' đã được xóa thành công!");
    }
}
