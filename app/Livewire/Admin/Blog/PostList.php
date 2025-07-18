<?php

namespace App\Livewire\Admin\Blog;

use App\Models\Post;
use Livewire\Component;
use Livewire\WithPagination;

class PostList extends Component
{
    use WithPagination;

    public function delete($id)
    {
        Post::find($id)->delete();
        session()->flash('message', 'Post Deleted Successfully.');
    }

    public function render()
    {
        return view('livewire.admin.blog.post-list', [
            'posts' => Post::with('user')->latest()->paginate(10),
        ])->layout('layouts.sidebar');
    }
}
