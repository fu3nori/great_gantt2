@foreach($comments as $comment)
<article class="comment-item" style="--depth:{{ $depth }}"><div class="avatar">{{ mb_substr($comment->user?->name ?? '?',0,1) }}</div><div class="comment-body"><header><strong>{{ $comment->user?->name ?? '退会ユーザー' }}</strong><time>{{ $comment->created_at->format('Y/m/d H:i') }}</time></header><p>{{ $comment->body }}</p><button class="btn btn-link btn-sm p-0 reply-button" data-comment-id="{{ $comment->id }}" data-comment-name="{{ $comment->user?->name }}"><i class="bi bi-reply"></i> 返信</button></div></article>
@if($comment->replies->isNotEmpty())<div class="comment-replies">@include('tasks._comments',['comments'=>$comment->replies,'depth'=>$depth+1])</div>@endif
@endforeach
