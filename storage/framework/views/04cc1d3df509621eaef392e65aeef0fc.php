<?php $__env->startSection('title', 'Обращение'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Обращение #<?php echo e($callCenterContact->id); ?></h1>
    <div>
        <a href="<?php echo e(route('call-center.edit', $callCenterContact)); ?>" class="btn btn-outline-primary">Изменить</a>
        <a href="<?php echo e(route('call-center.index')); ?>" class="btn btn-secondary">К списку</a>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Канал и контакт</div>
            <div class="card-body">
                <p><strong>Канал:</strong> <?php echo e($callCenterContact->channel_label); ?></p>
                <p><strong>Направление:</strong> <?php echo e($callCenterContact->direction === 'incoming' ? 'Входящее' : 'Исходящее'); ?></p>
                <?php if($callCenterContact->call_status): ?>
                    <p><strong>Статус вызова:</strong> <?php echo e($callCenterContact->call_status_label); ?></p>
                <?php endif; ?>
                <p><strong>Дата:</strong> <?php echo e($callCenterContact->contact_date ? \Carbon\Carbon::parse($callCenterContact->contact_date)->format('d.m.Y H:i') : '—'); ?></p>
                <p><strong>Магазин:</strong> <?php echo e($callCenterContact->store?->name ?? '—'); ?></p>
                <hr>
                <?php if($callCenterContact->client_id): ?>
                    <p><strong>Клиент:</strong> <a href="<?php echo e(route('clients.show', $callCenterContact->client)); ?>"><?php echo e($callCenterContact->client->full_name); ?></a></p>
                    <p><strong>Телефон:</strong> <?php echo e($callCenterContact->client->phone ?? '—'); ?></p>
                <?php else: ?>
                    <p><strong>Контакт:</strong> <?php echo e($callCenterContact->contact_name ?: '—'); ?></p>
                    <p><strong>Телефон:</strong> <?php echo e($callCenterContact->contact_phone ?: '—'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Исход и сделка</div>
            <div class="card-body">
                <p><strong>Исход:</strong> <?php echo e($callCenterContact->outcome_label); ?></p>
                <?php if($callCenterContact->linkedContract): ?>
                    <p><strong>Сделка:</strong>
                        <?php if($callCenterContact->pawn_contract_id): ?>
                            <a href="<?php echo e(route('pawn-contracts.show', $callCenterContact->pawnContract)); ?>"><?php echo e($callCenterContact->pawnContract->contract_number); ?></a> (залог)
                        <?php elseif($callCenterContact->purchase_contract_id): ?>
                            <a href="<?php echo e(route('purchase-contracts.show', $callCenterContact->purchaseContract)); ?>"><?php echo e($callCenterContact->purchaseContract->contract_number); ?></a> (скупка)
                        <?php elseif($callCenterContact->commission_contract_id): ?>
                            <a href="<?php echo e(route('commission-contracts.show', $callCenterContact->commissionContract)); ?>"><?php echo e($callCenterContact->commissionContract->contract_number); ?></a> (комиссия)
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <?php if($callCenterContact->createdByUser): ?>
                    <p><strong>Зафиксировал:</strong> <?php echo e($callCenterContact->createdByUser->name); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php if($callCenterContact->notes): ?>
<div class="card mb-4">
    <div class="card-header">Заметки</div>
    <div class="card-body"><?php echo e(nl2br(e($callCenterContact->notes))); ?></div>
</div>
<?php endif; ?>
<?php if($callCenterContact->channel === 'phone' && ($callCenterContact->recording_path || ($callCenterContact->external_id && str_starts_with($callCenterContact->external_id, 'mts_')))): ?>
<div class="card mb-4" id="recording">
    <div class="card-header">Запись разговора</div>
    <div class="card-body">
        <?php if($callCenterContact->recording_path): ?>
            <audio controls preload="metadata" class="w-100" style="max-width: 400px;">
                <source src="<?php echo e(route('call-center.recording', $callCenterContact)); ?>" type="audio/mpeg">
                Ваш браузер не поддерживает воспроизведение.
            </audio>
            <p class="mt-2 mb-0"><a href="<?php echo e(route('call-center.recording', $callCenterContact)); ?>?download=1" class="btn btn-sm btn-outline-secondary">Скачать MP3</a></p>
        <?php elseif($callCenterContact->external_id && str_starts_with($callCenterContact->external_id, 'mts_')): ?>
            <p class="mb-2">Запись хранится в MTS. Можно попробовать получить её по ссылке (по идентификатору звонка).</p>
            <p class="mb-2">
                <a href="<?php echo e(route('call-center.recording-mts', $callCenterContact)); ?>" class="btn btn-sm btn-primary" target="_blank" rel="noopener"><i class="bi bi-play-circle"></i> Слушать запись с MTS</a>
                <a href="<?php echo e(route('call-center.recording-mts', $callCenterContact)); ?>?download=1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Скачать с MTS</a>
            </p>
            <p class="mb-0 small text-muted">
                <a href="https://vpbx.mts.ru" target="_blank" rel="noopener">Открыть историю вызовов в личном кабинете MTS</a> — там можно найти звонок по дате и прослушать запись.
            </p>
        <?php endif; ?>
        <hr class="my-3">
        <p class="mb-2 small text-muted">Расшифровка в текст: Whisper (распознавание речи) + DeepSeek (оформление). Нужны OPENAI_API_KEY и DEEPSEEK_API_KEY в .env.</p>
        <form method="post" action="<?php echo e(route('call-center.transcribe', $callCenterContact)); ?>" class="d-inline">
            <?php echo csrf_field(); ?>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-text-left"></i> <?php echo e($callCenterContact->recording_transcript ? 'Обновить расшифровку' : 'Транскрибировать в текст'); ?></button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if($callCenterContact->recording_transcript): ?>
<div class="card mb-4" id="transcript">
    <div class="card-header">Расшифровка разговора</div>
    <div class="card-body">
        <div class="bg-light rounded p-3" style="white-space: pre-wrap;"><?php echo e($callCenterContact->recording_transcript); ?></div>
    </div>
</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/call-center/show.blade.php ENDPATH**/ ?>