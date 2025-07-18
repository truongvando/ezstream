<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'slug' => 'required|unique:posts,slug',
            'body' => 'required',
            'external_link' => 'nullable|url',
            'status' => 'required|in:DRAFT,PUBLISHED',
        ]);

        $post = new Post();
        $post->user_id = auth()->id();
        $post->title = $request->title;
        $post->slug = Str::slug($request->slug);
        $post->body = $request->body;
        $post->excerpt = $request->excerpt;
        $post->external_link = $request->external_link;
        $post->status = $request->status;
        $post->meta_title = $request->meta_title;
        $post->meta_description = $request->meta_description;

        if ($request->hasFile('featured_image')) {
            $post->featured_image = $request->file('featured_image')->store('posts', 'public');
        }

        if ($request->status === 'PUBLISHED') {
            $post->published_at = now();
        }

        $post->save();

        return redirect()->route('admin.blog.index')->with('success', 'Post created successfully!');
    }

    public function edit(Post $post)
    {
        return view('admin.blog.edit', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        $request->validate([
            'title' => 'required|max:255',
            'slug' => 'required|unique:posts,slug,' . $post->id,
            'body' => 'required',
            'external_link' => 'nullable|url',
            'status' => 'required|in:DRAFT,PUBLISHED',
        ]);

        $post->title = $request->title;
        $post->slug = Str::slug($request->slug);
        $post->body = $request->body;
        $post->excerpt = $request->excerpt;
        $post->external_link = $request->external_link;
        $post->status = $request->status;
        $post->meta_title = $request->meta_title;
        $post->meta_description = $request->meta_description;

        if ($request->hasFile('featured_image')) {
            $post->featured_image = $request->file('featured_image')->store('posts', 'public');
        }

        if ($request->status === 'PUBLISHED' && !$post->published_at) {
            $post->published_at = now();
        }

        $post->save();

        return redirect()->route('admin.blog.index')->with('success', 'Post updated successfully!');
    }

    public function destroy(Post $post)
    {
        $post->delete();
        return redirect()->route('admin.blog.index')->with('success', 'Post deleted successfully!');
    }
}
