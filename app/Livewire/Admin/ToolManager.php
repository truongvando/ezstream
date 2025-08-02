<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Tool;
use App\Models\ToolOrder;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class ToolManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $showCreateModal = false;
    public $showEditModal = false;
    public $selectedTool = null;

    // Form fields
    public $name = '';
    public $slug = '';
    public $description = '';
    public $short_description = '';
    public $price = '';
    public $sale_price = '';
    public $image = '';
    public $features = [];
    public $system_requirements = '';
    public $download_url = '';
    public $demo_url = '';
    public $is_active = true;
    public $is_featured = false;
    public $sort_order = 0;

    protected $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'required|string|max:255|unique:tools,slug',
        'description' => 'required|string',
        'short_description' => 'required|string|max:500',
        'price' => 'required|numeric|min:0',
        'sale_price' => 'nullable|numeric|min:0',
        'image' => 'required|string',
        'system_requirements' => 'nullable|string',
        'download_url' => 'required|string',
        'demo_url' => 'nullable|string',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer|min:0'
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function showCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function showEditModal($toolId)
    {
        $tool = Tool::findOrFail($toolId);
        $this->selectedTool = $tool;
        
        $this->name = $tool->name;
        $this->slug = $tool->slug;
        $this->description = $tool->description;
        $this->short_description = $tool->short_description;
        $this->price = $tool->price;
        $this->sale_price = $tool->sale_price;
        $this->image = $tool->image;
        $this->features = $tool->features ?? [];
        $this->system_requirements = $tool->system_requirements;
        $this->download_url = $tool->download_url;
        $this->demo_url = $tool->demo_url;
        $this->is_active = $tool->is_active;
        $this->is_featured = $tool->is_featured;
        $this->sort_order = $tool->sort_order;
        
        $this->showEditModal = true;
    }

    public function closeModals()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->selectedTool = null;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->short_description = '';
        $this->price = 0;
        $this->sale_price = null;
        $this->image = 'https://via.placeholder.com/400x300?text=Tool+Image';
        $this->features = [];
        $this->system_requirements = '';
        $this->download_url = '';
        $this->demo_url = '';
        $this->is_active = true;
        $this->is_featured = false;
        $this->sort_order = 0;
    }

    public function createTool()
    {
        $this->validate();

        try {
            Tool::create([
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'short_description' => $this->short_description,
                'price' => $this->price,
                'sale_price' => $this->sale_price ?: null,
                'image' => $this->image,
                'features' => $this->features,
                'system_requirements' => $this->system_requirements,
                'download_url' => $this->download_url,
                'demo_url' => $this->demo_url ?: null,
                'is_active' => $this->is_active,
                'is_featured' => $this->is_featured,
                'sort_order' => $this->sort_order,
            ]);

            session()->flash('success', 'Tool created successfully!');
            $this->closeModals();

        } catch (\Exception $e) {
            Log::error('Error creating tool: ' . $e->getMessage());
            session()->flash('error', 'Error creating tool.');
        }
    }

    public function updateTool()
    {
        $this->validate([
            'slug' => 'required|string|max:255|unique:tools,slug,' . $this->selectedTool->id,
        ] + array_slice($this->rules, 1));

        try {
            $this->selectedTool->update([
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'short_description' => $this->short_description,
                'price' => $this->price,
                'sale_price' => $this->sale_price ?: null,
                'image' => $this->image,
                'features' => $this->features,
                'system_requirements' => $this->system_requirements,
                'download_url' => $this->download_url,
                'demo_url' => $this->demo_url ?: null,
                'is_active' => $this->is_active,
                'is_featured' => $this->is_featured,
                'sort_order' => $this->sort_order,
            ]);

            session()->flash('success', 'Tool updated successfully!');
            $this->closeModals();

        } catch (\Exception $e) {
            Log::error('Error updating tool: ' . $e->getMessage());
            session()->flash('error', 'Error updating tool.');
        }
    }

    public function deleteTool($toolId)
    {
        try {
            $tool = Tool::findOrFail($toolId);
            
            // Check if tool has orders
            $orderCount = ToolOrder::where('tool_id', $toolId)->count();
            if ($orderCount > 0) {
                session()->flash('error', "Cannot delete tool. It has {$orderCount} orders.");
                return;
            }

            $tool->delete();
            session()->flash('success', 'Tool deleted successfully!');

        } catch (\Exception $e) {
            Log::error('Error deleting tool: ' . $e->getMessage());
            session()->flash('error', 'Error deleting tool.');
        }
    }

    public function toggleStatus($toolId)
    {
        try {
            $tool = Tool::findOrFail($toolId);
            $tool->update(['is_active' => !$tool->is_active]);
            
            $status = $tool->is_active ? 'activated' : 'deactivated';
            session()->flash('success', "Tool {$status} successfully!");

        } catch (\Exception $e) {
            Log::error('Error toggling tool status: ' . $e->getMessage());
            session()->flash('error', 'Error updating tool status.');
        }
    }

    public function render()
    {
        $query = Tool::query();

        // Search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        // Status filter
        switch ($this->statusFilter) {
            case 'active':
                $query->where('is_active', true);
                break;
            case 'inactive':
                $query->where('is_active', false);
                break;
            case 'featured':
                $query->where('is_featured', true);
                break;
        }

        $tools = $query->withCount('toolOrders')
                      ->orderBy('sort_order')
                      ->orderBy('created_at', 'desc')
                      ->paginate(10);

        // Get statistics
        $stats = [
            'total_tools' => Tool::count(),
            'active_tools' => Tool::where('is_active', true)->count(),
            'featured_tools' => Tool::where('is_featured', true)->count(),
            'total_orders' => ToolOrder::count(),
            'completed_orders' => ToolOrder::where('status', 'COMPLETED')->count(),
        ];

        return view('livewire.admin.tool-manager', [
            'tools' => $tools,
            'stats' => $stats
        ]);
    }
}
