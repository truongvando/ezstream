<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Tool;

#[Layout('layouts.sidebar')]
class ToolStore extends Component
{
    use WithPagination;

    public $search = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $showFeaturedOnly = false;
    public $priceRange = 'all';

    protected $queryString = [
        'search' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
        'showFeaturedOnly' => ['except' => false],
        'priceRange' => ['except' => 'all']
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingShowFeaturedOnly()
    {
        $this->resetPage();
    }

    public function updatingPriceRange()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'sortBy', 'sortDirection', 'showFeaturedOnly', 'priceRange']);
        $this->resetPage();
    }

    public function render()
    {
        $query = Tool::where('is_active', true);

        // Search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhere('short_description', 'like', '%' . $this->search . '%');
            });
        }

        // Featured filter
        if ($this->showFeaturedOnly) {
            $query->where('is_featured', true);
        }

        // Price range filter
        switch ($this->priceRange) {
            case 'under_200k':
                $query->where(function($q) {
                    $q->where('sale_price', '<', 200000)
                      ->orWhere(function($q2) {
                          $q2->whereNull('sale_price')->where('price', '<', 200000);
                      });
                });
                break;
            case '200k_500k':
                $query->where(function($q) {
                    $q->whereBetween('sale_price', [200000, 500000])
                      ->orWhere(function($q2) {
                          $q2->whereNull('sale_price')->whereBetween('price', [200000, 500000]);
                      });
                });
                break;
            case 'over_500k':
                $query->where(function($q) {
                    $q->where('sale_price', '>', 500000)
                      ->orWhere(function($q2) {
                          $q2->whereNull('sale_price')->where('price', '>', 500000);
                      });
                });
                break;
        }

        // Sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        // If sorting by price, we need to handle sale_price vs price
        if ($this->sortBy === 'price') {
            $query->orderByRaw('COALESCE(sale_price, price) ' . $this->sortDirection);
        }

        $tools = $query->paginate(12);

        // Get statistics
        $stats = [
            'total_tools' => Tool::where('is_active', true)->count(),
            'featured_tools' => Tool::where('is_active', true)->where('is_featured', true)->count(),
            'on_sale_tools' => Tool::where('is_active', true)->whereNotNull('sale_price')->count(),
        ];

        return view('livewire.tool-store', [
            'tools' => $tools,
            'stats' => $stats
        ]);
    }
}
