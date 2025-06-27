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
    public $features = [];
    public $newFeature = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'max_streams' => 'required|integer|min:1',
            'storage_limit_gb' => 'required|integer|min:1',
            'is_active' => 'boolean',
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
        $this->is_active = true;
        $this->features = [];
        $this->newFeature = '';
        $this->resetErrorBag();
    }

    public function addFeature()
    {
        if (trim($this->newFeature) !== '') {
            $this->features[] = trim($this->newFeature);
            $this->newFeature = '';
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
        $dataToSave['storage_limit'] = $validatedData['storage_limit_gb'] * 1024 * 1024 * 1024;

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
        $this->storage_limit_gb = $package->storage_limit ? round($package->storage_limit / (1024*1024*1024)) : 0;
        $this->is_active = $package->is_active;
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
}
