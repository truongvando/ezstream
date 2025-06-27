<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $showEditModal = false;
    public $showDeleteModal = false;
    public $editingUser;
    public $deletingUser;

    // Form fields
    public $name, $email, $role;

    protected $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'role' => 'required|in:admin,user',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
    }

    public function edit(User $user)
    {
        $this->resetValidation();
        $this->editingUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->showEditModal = true;
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $this->editingUser->id,
            'role' => 'required|in:admin,user',
        ]);

        $this->editingUser->update([
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ]);

        $this->showEditModal = false;
        session()->flash('success', 'User updated successfully.');
    }

    public function confirmDelete(User $user)
    {
        $this->deletingUser = $user;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if ($this->deletingUser->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            $this->showDeleteModal = false;
            return;
        }

        $this->deletingUser->delete();
        $this->showDeleteModal = false;
        session()->flash('success', 'User deleted successfully.');
    }

    public function closeModal()
    {
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->reset(['name', 'email', 'role']);
    }

    public function render()
    {
        $query = User::query();

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->sortField === 'role') {
            $query->orderBy('role', $this->sortDirection);
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $users = $query->paginate(10);

        return view('livewire.admin.user-management', compact('users'))
            ->layout('layouts.sidebar')
        ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Quản lý Users</h1>');
    }
}
