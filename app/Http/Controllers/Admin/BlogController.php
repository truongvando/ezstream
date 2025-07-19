<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\BunnyStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BlogController extends Controller
{
    public function index()
    {
        $posts = Post::with('user')->latest()->paginate(10);
        return view('admin.blog.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.blog.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:255',
            'link' => 'required|url|max:255',
            'status' => 'required|in:DRAFT,PUBLISHED',
            'featured_image' => 'nullable|image|max:2048',
        ]);

        Log::info('Blog post store method called', [
            'has_file' => $request->hasFile('featured_image'),
            'request_data' => $request->only(['title', 'link', 'status'])
        ]);

        $post = new Post();
        $post->user_id = auth()->id();
        $post->title = $request->title;
        $post->link = $request->link;
        $post->status = $request->status;
        $post->slug = Str::slug($request->title) . '-' . uniqid();

        // Upload to BunnyCDN via BunnyStorageService
        if ($request->hasFile('featured_image')) {
            $file = $request->file('featured_image');

            Log::info('Blog image upload started', [
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
                $tempPath = sys_get_temp_dir() . '/' . Str::random(32) . '.jpg';
                $image->toJpeg(80)->save($tempPath);

                // Upload using BunnyStorageService
                $bunnyService = new BunnyStorageService();
                $uploadResult = $bunnyService->uploadFile($tempPath, 'post_' . time() . '.jpg', 'image/jpeg');

                // Clean up temp file
                unlink($tempPath);

                if ($uploadResult['success']) {
                    $post->featured_image = $uploadResult['cdn_url'];
                    Log::info('Blog image upload successful', [
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

                Log::error('Blog image upload failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['featured_image' => 'Failed to upload image: ' . $e->getMessage()])->withInput();
            }
        }

        if ($request->status === 'PUBLISHED') {
            $post->published_at = now();
        }

        $post->save();

        return redirect()->route('admin.blog.index')->with('success', 'Link Card created successfully!');
    }

    public function edit(Post $post)
    {
        return view('admin.blog.edit', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        $request->validate([
            'title' => 'required|max:255',
            'link' => 'required|url|max:255',
            'status' => 'required|in:DRAFT,PUBLISHED',
            'featured_image' => 'nullable|image|max:2048',
        ]);

        Log::info('Blog post update method called', [
            'post_id' => $post->id,
            'has_file' => $request->hasFile('featured_image'),
            'request_data' => $request->only(['title', 'link', 'status'])
        ]);

        $post->title = $request->title;
        $post->link = $request->link;
        $post->status = $request->status;
        $post->slug = Str::slug($request->title) . '-' . uniqid();

        // Upload to BunnyCDN via BunnyStorageService
        if ($request->hasFile('featured_image')) {
            $file = $request->file('featured_image');

            Log::info('Blog image update upload started', [
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
                $tempPath = sys_get_temp_dir() . '/' . Str::random(32) . '.jpg';
                $image->toJpeg(80)->save($tempPath);

                // Upload using BunnyStorageService
                $bunnyService = new BunnyStorageService();
                $uploadResult = $bunnyService->uploadFile($tempPath, 'post_' . time() . '.jpg', 'image/jpeg');

                // Clean up temp file
                unlink($tempPath);

                if ($uploadResult['success']) {
                    $post->featured_image = $uploadResult['cdn_url'];
                    Log::info('Blog image update upload successful', [
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

                Log::error('Blog image update upload failed', ['error' => $e->getMessage()]);
                return back()->withErrors(['featured_image' => 'Failed to upload image: ' . $e->getMessage()])->withInput();
            }
        }

        if ($request->status === 'PUBLISHED' && !$post->published_at) {
            $post->published_at = now();
        }

        $post->save();

        return redirect()->route('admin.blog.index')->with('success', 'Link Card updated successfully!');
    }

    public function destroy(Post $post)
    {
        $post->delete();
        return redirect()->route('admin.blog.index')->with('success', 'Post deleted successfully!');
    }
}
