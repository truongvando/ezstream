<?php

namespace App\Livewire\Admin\Blog;

use App\Models\Post;
use App\Services\BunnyStorageService;
use Illuminate\Support\Str;
use Livewire\Component;
// use Livewire\WithFileUploads; // Disabled for form upload

class PostForm extends Component
{
    // use WithFileUploads; // Disabled for form upload

    public ?Post $post = null;
    public $title, $link, $status;
    // public $featured_image; // Disabled for form upload
    public $existing_featured_image;

    protected function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'link' => 'required|url|max:255',
            'status' => 'required|in:DRAFT,PUBLISHED',
            // 'featured_image' => 'nullable|image|max:2048', // Disabled for form upload
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
        \Illuminate\Support\Facades\Log::info('PostForm save method called', [
            'has_file' => request()->hasFile('featured_image'),
            'request_files' => request()->allFiles()
        ]);

        $this->validate();

        $this->post->user_id = auth()->id();
        $this->post->title = $this->title;
        $this->post->link = $this->link;
        $this->post->status = $this->status;

        // Ensure slug is generated but not required from form
        $this->post->slug = Str::slug($this->title) . '-' . uniqid();


        // Upload to BunnyCDN via BunnyStorageService
        if (request()->hasFile('featured_image')) {
            $file = request()->file('featured_image');

            \Illuminate\Support\Facades\Log::info('Blog image upload started', [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ]);

            try {
                // Process image first
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $image = $manager->read($file->getRealPath());
                $image->cover(800, 450);

                // Save to temp file
                $tempPath = sys_get_temp_dir() . '/' . \Illuminate\Support\Str::random(32) . '.jpg';
                $image->toJpeg(80)->save($tempPath);

                // Upload using BunnyStorageService
                $bunnyService = new BunnyStorageService();
                $uploadResult = $bunnyService->uploadFile($tempPath, 'post_' . time() . '.jpg', 'image/jpeg');

                // Clean up temp file
                unlink($tempPath);

                if ($uploadResult['success']) {
                    $this->post->featured_image = $uploadResult['cdn_url'];
                    \Illuminate\Support\Facades\Log::info('Blog image upload successful', [
                        'cdn_url' => $uploadResult['cdn_url']
                    ]);
                } else {
                    throw new \Exception($uploadResult['error']);
                }

            } catch (\Exception $e) {
                // Clean up temp file if exists
                if (isset($tempPath) && file_exists($tempPath)) {
                    unlink($tempPath);
                }

                session()->flash('error', 'Failed to upload image: ' . $e->getMessage());
                return;
            }
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
