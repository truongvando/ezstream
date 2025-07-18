<?php

namespace App\Livewire\Admin\Blog;

use App\Models\Post;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class PostForm extends Component
{
    use WithFileUploads;

    public ?Post $post = null;
    public $title, $link, $status;
    public $featured_image;
    public $existing_featured_image;

    protected function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'link' => 'required|url|max:255',
            'status' => 'required|in:DRAFT,PUBLISHED',
            'featured_image' => 'nullable|image|max:2048',
        ];
    }

    public function mount($postId = null)
    {
        if ($postId) {
            $this->post = Post::findOrFail($postId);
            $this->title = $this->post->title;
            $this->link = $this->post->link;
            $this->status = $this->post->status;
            $this->existing_featured_image = $this->post->featured_image;
        } else {
            $this->post = new Post();
            $this->status = 'DRAFT';
        }
    }

    public function save()
    {
        $this->validate();

        $this->post->user_id = auth()->id();
        $this->post->title = $this->title;
        $this->post->link = $this->link;
        $this->post->status = $this->status;

        // Ensure slug is generated but not required from form
        $this->post->slug = Str::slug($this->title) . '-' . uniqid();


        if ($this->featured_image) {
            // Create image manager with GD driver
            $manager = new ImageManager(new Driver());

            // Read image from temporary upload path
            $image = $manager->read($this->featured_image->getRealPath());

            // Resize and crop the image to a 800x450
            $image->cover(800, 450);

            // Generate a unique name for the image
            $imageName = Str::random(32) . '.jpg';
            $path = 'posts/' . $imageName;

            // Save the processed image to the public storage disk
            $image->toJpeg(80)->save(storage_path('app/public/' . $path));

            // Store the new path in the database
            $this->post->featured_image = $path;
        }

        $this->post->save();

        session()->flash('message', $this->post->wasRecentlyCreated ? 'Link Card Created Successfully.' : 'Link Card Updated Successfully.');

        return redirect()->route('admin.blog.index');
    }


    public function render()
    {
        return view('livewire.admin.blog.post-form')->layout('layouts.sidebar');
    }
}
