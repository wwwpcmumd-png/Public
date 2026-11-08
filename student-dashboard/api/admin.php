<?php
declare(strict_types=1);
/**
 * Internal JSON API used by admin/index.php.
 * Every request: valid session + admin role (re-checked in the DB).
 * Every mutation: POST + CSRF token (X-CSRF-Token header).
 */
define('IS_API', true);
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

api_require_admin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    json_response(['ok' => true, 'state' => build_state(parse_filters($_GET))]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'message' => 'الطريقة غير مدعومة.'], 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_valid($token)) {
    json_response(['ok' => false, 'message' => 'رمز الحماية غير صالح، أعد تحميل الصفحة.'], 403);
}

$in = read_json_body();
$action = is_string($in['action'] ?? null) ? $in['action'] : '';
$filters = parse_filters(is_array($in['filters'] ?? null) ? $in['filters'] : []);

switch ($action) {
    case 'student_save':
        $message = save_student($in);
        break;
    case 'student_delete':
        $message = delete_student((int)($in['id'] ?? 0));
        break;
    case 'score_adjust':
        $message = adjust_score((int)($in['id'] ?? 0), $in['delta'] ?? null);
        break;
    case 'group_add':
        $message = add_group($in['name'] ?? '');
        break;
    case 'group_delete':
        $message = delete_group((int)($in['id'] ?? 0));
        break;
    default:
        json_response(['ok' => false, 'message' => 'عملية غير معروفة.'], 400);
}

json_response(['ok' => true, 'message' => $message, 'state' => build_state($filters)]);
