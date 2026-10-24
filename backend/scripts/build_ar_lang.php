<?php

declare(strict_types=1);

$json = file_get_contents('https://cdn.jsdelivr.net/gh/Laravel-Lang/lang@main/locales/ar/php.json');
$flat = json_decode((string) $json, true);

if (! is_array($flat)) {
    fwrite(STDERR, "Failed to download Arabic translations.\n");
    exit(1);
}

/**
 * @param  array<string, string>  $flat
 * @return array<string, mixed>
 */
function nest(array $flat): array
{
    $out = [];

    foreach ($flat as $key => $value) {
        $parts = explode('.', $key);
        $ref = &$out;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $ref[$part] = $value;
            } else {
                if (! isset($ref[$part]) || ! is_array($ref[$part])) {
                    $ref[$part] = [];
                }
                $ref = &$ref[$part];
            }
        }

        unset($ref);
    }

    return $out;
}

/**
 * @param  array<string, mixed>  $data
 */
function writeLang(string $path, array $data): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $export = var_export($data, true);
    file_put_contents($path, "<?php\n\nreturn ".$export.";\n");
}

$nested = nest($flat);

$auth = [
    'failed' => $flat['failed'] ?? 'بيانات الدخول غير صحيحة.',
    'password' => is_string($flat['password'] ?? null) ? $flat['password'] : 'كلمة المرور غير صحيحة.',
    'throttle' => $flat['throttle'] ?? 'محاولات كثيرة. حاول لاحقًا.',
];

$passwords = [
    'reset' => $flat['reset'] ?? 'تمت إعادة تعيين كلمة المرور.',
    'sent' => $flat['sent'] ?? 'تم إرسال رابط استعادة كلمة المرور.',
    'throttled' => $flat['throttled'] ?? 'يرجى الانتظار قبل إعادة المحاولة.',
    'token' => $flat['token'] ?? 'رمز استعادة كلمة المرور غير صالح.',
    'user' => $flat['user'] ?? 'لا يوجد حساب بهذا البريد الإلكتروني.',
];

$pagination = [
    'previous' => $flat['previous'] ?? '« السابق',
    'next' => $flat['next'] ?? 'التالي »',
];

$validation = $nested;
foreach (array_keys($auth + $passwords + $pagination) as $key) {
    unset($validation[$key]);
}

// Keep nested password rule messages under validation.password.*
if (isset($nested['password']) && is_array($nested['password'])) {
    $validation['password'] = $nested['password'];
}

$validation['attributes'] = [
    'name' => 'الاسم',
    'email' => 'البريد الإلكتروني',
    'password' => 'كلمة المرور',
    'password_confirmation' => 'تأكيد كلمة المرور',
    'phone' => 'رقم الجوال',
    'title' => 'العنوان',
    'slug' => 'المسار',
    'content' => 'المحتوى',
    'message' => 'الرسالة',
    'description' => 'الوصف',
    'status' => 'الحالة',
    'role' => 'الدور',
    'amount' => 'المبلغ',
    'file' => 'الملف',
    'image' => 'الصورة',
    'meta_title' => 'عنوان SEO',
    'meta_description' => 'وصف SEO',
    'og_image' => 'صورة المشاركة',
    'is_published' => 'حالة النشر',
    'show_in_footer' => 'الظهور في الفوتر',
    'footer_group' => 'مجموعة الفوتر',
    'footer_order' => 'ترتيب الفوتر',
    'page_type' => 'نوع الصفحة',
];
$validation['custom'] = [];

$base = dirname(__DIR__).'/lang/ar';
writeLang($base.'/validation.php', $validation);
writeLang($base.'/auth.php', $auth);
writeLang($base.'/passwords.php', $passwords);
writeLang($base.'/pagination.php', $pagination);

echo "Arabic lang files written to lang/ar\n";
