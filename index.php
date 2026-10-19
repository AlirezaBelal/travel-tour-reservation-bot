<?php

require __DIR__ . '/config.php';
require __DIR__ . '/txt.php';
try {
    require __DIR__ . '/src/handler.php';
} catch (InvalidArgumentException) {
    sendMessage($chatId, 'اطلاعات واردشده معتبر نیست یا دسته موردنظر دیگر وجود ندارد. لطفاً دوباره تلاش کنید.');
} catch (Throwable) {
    error_log('Webhook processing failed.');
    http_response_code(500);
}

