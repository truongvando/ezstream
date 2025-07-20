<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ServicePackage;

class ServicePackageManager extends Component
{
    use WithPagination;

    public $showModal = false;
    public $editingPackage = null;
    
    // Form fields
    public $name = '';
    public $description = '';
    public $price = '';
    public $max_streams = 1;
    public $storage_limit_gb = 10;
    public $is_active = true;
    public $is_popular = false;
    public $features = [];
    public $newFeature = '';
    public $max_video_width = 1920;
    public $max_video_height = 1080;

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'max_streams' => 'required|integer|min:1',
            'storage_limit_gb' => 'required|integer|min:1',
            'max_video_width' => 'required|integer|min:1',
            'max_video_height' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'features' => 'nullable|array',
        ];
    }
    
    public function render()
    {
        return view('livewire.service-package-manager', [
            'packages' => ServicePackage::paginate(10)
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Gói dịch vụ</h1>');
    }

    public function openModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingPackage = null;
        $this->name = '';
        $this->description = '';
        $this->price = '';
        $this->max_streams = 1;
        $this->storage_limit_gb = 10;
        $this->max_video_width = 1920;
        $this->max_video_height = 1080;
        $this->is_active = true;
        $this->is_popular = false;
        $this->features = [];
        $this->newFeature = '';
        $this->resetErrorBag();
    }

    public function addFeature()
    {
        $feature = trim($this->newFeature);
        if ($feature !== '') {
            // Kiểm tra trùng lặp
            if (!in_array($feature, $this->features)) {
                $this->features[] = $feature;
                $this->newFeature = '';

                // Thông báo thành công (optional)
                $this->dispatch('feature-added', $feature);
            } else {
                // Thông báo trùng lặp
                session()->flash('warning', 'Tính năng này đã tồn tại!');
            }
        }
    }

    public function removeFeature($index)
    {
        unset($this->features[$index]);
        $this->features = array_values($this->features);
    }

    public function save()
    {
        $validatedData = $this->validate();
        
        $dataToSave = $validatedData;
        // Remove storage_limit_gb from data to save, we'll save it as storage_limit_gb
        unset($dataToSave['storage_limit_gb']);
        
        // Save storage in GB (not bytes)
        $dataToSave['storage_limit_gb'] = $validatedData['storage_limit_gb'];

        if ($this->editingPackage) {
            $this->editingPackage->update($dataToSave);
            session()->flash('message', 'Gói dịch vụ đã được cập nhật thành công!');
        } else {
            ServicePackage::create($dataToSave);
            session()->flash('message', 'Gói dịch vụ đã được thêm thành công!');
        }

        $this->closeModal();
    }

    public function edit(ServicePackage $package)
    {
        $this->editingPackage = $package;
        $this->name = $package->name;
        $this->description = $package->description ?? '';
        $this->price = $package->price;
        $this->max_streams = $package->max_streams;
        $this->storage_limit_gb = $package->storage_limit_gb ?? 0; // Use GB column directly
        $this->max_video_width = $package->max_video_width ?? 1920;
        $this->max_video_height = $package->max_video_height ?? 1080;
        $this->is_active = $package->is_active;
        $this->is_popular = $package->is_popular ?? false;
        $this->features = $package->features ?? [];
        $this->showModal = true;
    }

    public function delete(ServicePackage $package)
    {
        $package->delete();
        session()->flash('message', 'Gói dịch vụ đã được xóa thành công!');
    }

    public function toggleStatus(ServicePackage $package)
    {
        $package->update(['is_active' => !$package->is_active]);
        session()->flash('message', 'Trạng thái gói dịch vụ đã được cập nhật!');
    }

    public function togglePopular(ServicePackage $package)
    {
        // Chỉ cho phép 1 gói popular tại 1 thời điểm
        if (!$package->is_popular) {
            // Bỏ popular cho tất cả gói khác
            ServicePackage::where('is_popular', true)->update(['is_popular' => false]);
        }
        
        $package->update(['is_popular' => !$package->is_popular]);
        session()->flash('message', 'Trạng thái phổ biến đã được cập nhật!');
    }
}
