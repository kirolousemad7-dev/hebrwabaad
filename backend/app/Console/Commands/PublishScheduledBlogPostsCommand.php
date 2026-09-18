<?php

namespace App\Console\Commands;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Illuminate\Console\Command;

class PublishScheduledBlogPostsCommand extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Flip overdue scheduled blog posts to published status';

    public function handle(): int
    {
        $count = BlogPost::query()
            ->where('status', BlogPostStatus::Scheduled)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->update([
                'status' => BlogPostStatus::Published->value,
            ]);

        $this->info("Published {$count} scheduled blog post(s).");

        return self::SUCCESS;
    }
}
