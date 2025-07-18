<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $posts = Post::where('status', 'PUBLISHED')->latest()->paginate(10);
        return view('blog.index', compact('posts'));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show(Post $post)
    {
        // For link cards, redirect immediately to the target link
        if ($post->link) {
            return redirect()->away($post->link);
        }

        // Redirect if the post is not published, unless the user is an admin
        if ($post->status !== 'PUBLISHED' && !(auth()->check() && auth()->user()->isAdmin())) {
            return redirect()->route('public.blog.index')->with('error', 'Bài viết không tồn tại hoặc chưa được xuất bản.');
        }
        
        return view('blog.show', compact('post'));
    }
}
