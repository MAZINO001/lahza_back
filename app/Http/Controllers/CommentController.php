<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    protected $models = [
        'invoice' => \App\Models\Invoice::class,
        'payment' => \App\Models\Payment::class,
        'project' => \App\Models\Project::class,
        'clients' => \App\Models\Client::class,
        'offers' => \App\Models\Offer::class,
        'quotes' => \App\Models\Quotes::class,
        'services' => \App\Models\Service::class,
        'team' => \App\Models\TeamUser::class,
        'intern' => \App\Models\Intern::class,
        'other' => \App\Models\Other::class,
        'users' => \App\Models\User::class,
    ];

    // List comments
    public function index($type, $id)
    {
        $model = $this->getModel($type)::findOrFail($id);
        return response()->json($model->comments()->latest()->get());
    }
    public function getAllComments()
    {
        return response()->json(Comment::all());
    }
public function getUserComments($userId)
{
    $comments = Comment::where('user_id', $userId)->latest()->get();

    return response()->json($comments);
}
    // Create comment
    public function store(Request $request, $type, $id)
    {
        $request->validate([
            'body' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        $model = $this->getModel($type)::findOrFail($id);

        $comment = $model->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->body,
            'is_internal' => $request->boolean('is_internal', false),
        ]);

        return response()->json($comment, 201);
    }

    protected function getModel($type)
    {
        if (!isset($this->models[$type])) {
            abort(404, 'Invalid comment type');
        }
        return $this->models[$type];
    }
/**
 * Remove the specified comment from storage.
 *
 * @param  \App\Models\Comment  $comment
 * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
 */
public function destroy(Comment $comment)
{
    // Uncomment and modify this if you have user authorization in place
    // if (auth()->user()->id !== $comment->user_id && !auth()->user()->is_admin) {
    //     return response()->json(['error' => 'Unauthorized'], 403);
    // }

    $comment->delete();

    return response()->json([
        'status' => 'success',
        'message' => 'Comment deleted successfully'
    ]);
}

/**
 * @deprecated Use destroy() instead
 */
public function deletecomments(Comment $comment)
{
    return $this->destroy($comment);
}

}
