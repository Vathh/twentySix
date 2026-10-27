@php
    $seed = $seed ?? null;
    $seedCounts = $seed['reactions']['counts'] ?? [];
    $seedLikes = is_object($seedCounts) ? (int) ($seedCounts->like ?? 0) : (int) ($seedCounts['like'] ?? 0);
    $seedComments = (int) ($seed['commentCount'] ?? 0);
@endphp

<div class="feed-post-bar">
    <button type="button" class="feed-post-action" @click="toggleLike()" :class="liked && 'is-on'" :disabled="sending" aria-label="Lubię to">
        <svg viewBox="0 0 24 24" class="feed-heart" aria-hidden="true">
            <path d="M12 20.5s-6.4-4.1-8.9-7.8C1.2 10.2 1.6 6.8 4.3 5.4c2-.1 3.8.8 4.8 2.4L12 11l2.9-3.2c1-1.6 2.8-2.5 4.8-2.4 2.7 1.4 3.1 4.8 1.2 7.3-2.5 3.7-8.9 7.8-8.9 7.8z"/>
        </svg>
        <span x-text="likeCount">{{ $seedLikes }}</span>
    </button>
    <button type="button" class="feed-post-action" @click="toggleComments()" :class="open && 'is-on'">
        <span>Komentarze</span>
        <span x-text="commentCount">{{ $seedComments }}</span>
    </button>
</div>
<p class="feed-post-error" x-show="error" x-cloak x-text="error"></p>
<div class="feed-post-thread" x-show="open" x-cloak>
    <p class="feed-post-note" x-show="loading">Wczytywanie…</p>
    <template x-for="comment in comments" :key="comment.id">
        <div class="feed-comment">
            <div class="feed-comment-head">
                <a class="feed-comment-author" :href="comment.author.playerId ? ('/players/' + comment.author.playerId) : null" x-text="comment.author.name"></a>
                <span class="feed-comment-time" x-text="ago(comment.createdAt)"></span>
            </div>
            <p class="feed-comment-body" x-text="comment.body"></p>
            <div class="feed-comment-actions">
                <button type="button" class="feed-post-action" @click="likeComment(comment)" :class="comment.reactions.mine === 'like' && 'is-on'">
                    <span x-text="likesOf(comment)"></span>
                </button>
                <button type="button" class="feed-post-action" @click="startReply(comment)">Odpowiedz</button>
                <button type="button" class="feed-post-action" x-show="comment.canDelete" @click="remove(comment)" x-text="pendingDelete === comment.id ? 'Usuń na pewno' : 'Usuń'"></button>
            </div>
            <template x-for="reply in comment.replies" :key="reply.id">
                <div class="feed-comment is-reply">
                    <div class="feed-comment-head">
                        <a class="feed-comment-author" :href="reply.author.playerId ? ('/players/' + reply.author.playerId) : null" x-text="reply.author.name"></a>
                        <span class="feed-comment-time" x-text="ago(reply.createdAt)"></span>
                    </div>
                    <p class="feed-comment-body" x-text="reply.body"></p>
                    <div class="feed-comment-actions">
                        <button type="button" class="feed-post-action" @click="likeComment(reply)" :class="reply.reactions.mine === 'like' && 'is-on'">
                            <span x-text="likesOf(reply)"></span>
                        </button>
                        <button type="button" class="feed-post-action" x-show="reply.canDelete" @click="remove(reply)" x-text="pendingDelete === reply.id ? 'Usuń na pewno' : 'Usuń'"></button>
                    </div>
                </div>
            </template>
        </div>
    </template>
    <form class="feed-composer" @submit.prevent="send()" x-show="canEngage">
        <p class="feed-post-note" x-show="replyTo">
            <span>Odpowiedź dla </span><span x-text="replyTo ? replyTo.name : ''"></span>
            <button type="button" class="feed-post-action" @click="replyTo = null">Anuluj</button>
        </p>
        <textarea class="feed-composer-input" rows="2" maxlength="500" placeholder="Napisz komentarz…" x-model="body"></textarea>
        <button type="submit" class="btn btn-secondary" :disabled="sending || body.trim() === ''">Wyślij</button>
    </form>
</div>
