<?php $__env->startSection('title', $article->title); ?>

<?php $__env->startSection('content'); ?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo e(route('kb.index')); ?>">База знаний</a></li>
        <li class="breadcrumb-item"><a href="<?php echo e(route('kb.category', $category->slug)); ?>"><?php echo e($category->name); ?></a></li>
        <li class="breadcrumb-item active"><?php echo e($article->title); ?></li>
    </ol>
</nav>
<div class="card">
    <div class="card-body">
        <h1 class="h4 mb-3"><?php echo e($article->title); ?></h1>
        <?php if($article->author): ?><p class="text-muted small">Автор: <?php echo e($article->author->name); ?></p><?php endif; ?>
        <div class="kb-content mb-0">
            <?php echo nl2br(e($article->content ?? '')); ?>

        </div>

        <?php
            $articleImages = $article->images ?? [];
            $videoUrls = is_array($article->video_urls ?? null) ? $article->video_urls : [];
            $hasMaterials = count($articleImages) > 0 || count($videoUrls) > 0;
        ?>
        <?php if($hasMaterials): ?>
            <hr class="my-4">
            <h2 class="h5 mb-3">Методические материалы</h2>

            <?php if(count($articleImages) > 0): ?>
                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2">
                        <?php $__currentLoopData = $articleImages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $path): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if(is_string($path) && $path !== ''): ?>
                                <?php $imgUrl = $article->imageUrl($path); ?>
                                <?php if($imgUrl !== ''): ?>
                                    <a href="<?php echo e($imgUrl); ?>" target="_blank" rel="noopener" class="d-block">
                                        <img src="<?php echo e($imgUrl); ?>" alt="<?php echo e($article->title); ?>" class="rounded border img-fluid" style="max-height: 200px; object-fit: cover;" loading="lazy">
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if(count($videoUrls) > 0): ?>
                <div>
                    <ul class="list-unstyled mb-0">
                        <?php $__currentLoopData = $videoUrls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="mb-2">
                                <?php if(preg_match('/youtube\.com\/watch\?v=([\w-]+)|youtu\.be\/([\w-]+)/', $url, $m)): ?>
                                    <?php $vid = $m[1] ?? $m[2]; ?>
                                    <a href="<?php echo e($url); ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                        <i class="bi bi-play-circle-fill text-danger me-1"></i> Смотреть видео на YouTube
                                    </a>
                                <?php elseif(preg_match('/vimeo\.com\/(\d+)/', $url, $m)): ?>
                                    <a href="<?php echo e($url); ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                        <i class="bi bi-play-circle-fill me-1"></i> Смотреть видео на Vimeo
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo e($url); ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                        <i class="bi bi-play-circle me-1"></i> Смотреть видео
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<a href="<?php echo e(route('kb.category', $category->slug)); ?>" class="btn btn-secondary mt-3">← К списку статей</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/knowledge-base/show.blade.php ENDPATH**/ ?>