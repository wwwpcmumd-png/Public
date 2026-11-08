<?php
declare(strict_types=1);

const SORT_OPTIONS = [
    'scoreDesc' => 's.score DESC, s.name ASC, s.id ASC',
    'scoreAsc'  => 's.score ASC, s.name ASC, s.id ASC',
    'nameAsc'   => 's.name ASC, s.id ASC',
    'idAsc'     => 's.id ASC',
];

/** Sanitise search / filter / sort input (whitelist based). */
function parse_filters(array $src): array
{
    $q = (isset($src['q']) && is_string($src['q'])) ? mb_substr(trim($src['q']), 0, 100) : '';
    $g = $src['group'] ?? 'ALL';
    $group = ((is_int($g) || (is_string($g) && ctype_digit($g))) && (int)$g > 0) ? (int)$g : 'ALL';
    $sort = (isset($src['sort']) && is_string($src['sort']) && isset(SORT_OPTIONS[$src['sort']]))
        ? $src['sort'] : 'scoreDesc';
    return ['q' => $q, 'group' => $group, 'sort' => $sort];
}

/** Global rank for every student id. Equal scores share the same rank. */
function rank_map(): array
{
    $rows = db()->query('SELECT id, score FROM students ORDER BY score DESC, name ASC, id ASC')->fetchAll();
    $map = [];
    $i = 0;
    $rank = 0;
    $prev = null;
    foreach ($rows as $r) {
        $i++;
        $score = (int)$r['score'];
        if ($score !== $prev) {
            $rank = $i;
            $prev = $score;
        }
        $map[(int)$r['id']] = $rank;
    }
    return $map;
}

function fetch_groups(): array
{
    $rows = db()->query('SELECT id, name FROM `groups` ORDER BY id ASC')->fetchAll();
    return array_map(static fn($g) => ['id' => (int)$g['id'], 'name' => $g['name']], $rows);
}

/** Everything the admin dashboard needs in one payload. */
function build_state(array $filters): array
{
    $pdo   = db();
    $ranks = rank_map();

    $where  = [];
    $params = [];
    if ($filters['q'] !== '') {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
        $where[] = "(s.name LIKE :q1 ESCAPE '!' OR s.student_code LIKE :q2 ESCAPE '!')";
        $params[':q1'] = $like;
        $params[':q2'] = $like;
    }
    if ($filters['group'] !== 'ALL') {
        $where[] = 's.group_id = :gid';
        $params[':gid'] = (int)$filters['group'];
    }
    $sql = 'SELECT s.id, s.student_code, s.name, s.group_id, s.score, s.avatar, g.name AS group_name
            FROM students s JOIN `groups` g ON g.id = s.group_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY ' . SORT_OPTIONS[$filters['sort']];
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $students = [];
    foreach ($st->fetchAll() as $r) {
        $students[] = [
            'id'       => (int)$r['id'],
            'code'     => (string)$r['student_code'],
            'name'     => $r['name'],
            'group_id' => (int)$r['group_id'],
            'group'    => $r['group_name'],
            'score'    => (int)$r['score'],
            'avatar'   => $r['avatar'],
            'rank'     => $ranks[(int)$r['id']] ?? 0,
        ];
    }

    $top = $pdo->query(
        'SELECT s.id, s.name, s.score, s.avatar, g.name AS group_name
         FROM students s JOIN `groups` g ON g.id = s.group_id
         ORDER BY s.score DESC, s.name ASC, s.id ASC LIMIT 3'
    )->fetchAll();
    $podium = array_map(static fn($r) => [
        'id'     => (int)$r['id'],
        'name'   => $r['name'],
        'score'  => (int)$r['score'],
        'avatar' => $r['avatar'],
        'group'  => $r['group_name'],
    ], $top);

    $agg = $pdo->query('SELECT COUNT(*) AS c, AVG(score) AS a FROM students')->fetch();
    $totalStudents = (int)$agg['c'];
    $groups = fetch_groups();

    return [
        'groups'         => $groups,
        'students'       => $students,
        'podium'         => $podium,
        'total_students' => $totalStudents,
        'stats' => [
            'total_students' => $totalStudents,
            'total_groups'   => count($groups),
            'avg_score'      => $totalStudents > 0 ? round((float)$agg['a'], 1) : 0,
            'top_name'       => $podium[0]['name'] ?? null,
            'top_score'      => $podium[0]['score'] ?? 0,
        ],
    ];
}

/** Create or update a student. Returns the success message. */
function save_student(array $in): string
{
    $pdo = db();
    $id  = (isset($in['id']) && $in['id'] !== '' && $in['id'] !== null) ? (int)$in['id'] : 0;

    $name = clean_text($in['name'] ?? '');
    $len  = str_chars($name);
    if ($len < 2 || $len > 120) {
        throw new AppException('اسم الطالب يجب أن يتراوح بين 2 و 120 حرفاً.');
    }

    $score = filter_var($in['score'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
    if ($score === false || $score === null) {
        throw new AppException('التقييم يجب أن يكون رقماً صحيحاً بين 0 و 100.');
    }

    $avatar = is_string($in['avatar'] ?? null) ? $in['avatar'] : '';
    if (!in_array($avatar, avatar_options(), true)) {
        $avatar = default_avatar();
    }

    $groupId = filter_var($in['group_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($groupId === false || $groupId === null) {
        throw new AppException('يرجى اختيار المجموعة.');
    }
    $chk = $pdo->prepare('SELECT 1 FROM `groups` WHERE id = :id');
    $chk->execute([':id' => $groupId]);
    if (!$chk->fetchColumn()) {
        throw new AppException('المجموعة المحددة غير موجودة.');
    }

    $password = is_string($in['password'] ?? null) ? $in['password'] : '';
    if ($id === 0 && $password === '') {
        throw new AppException('كلمة مرور الطالب مطلوبة.');
    }
    if ($password !== '' && (strlen($password) < 6 || strlen($password) > 72)) {
        throw new AppException('كلمة المرور يجب أن تتراوح بين 6 و 72 حرفاً.');
    }

    if ($id > 0) {
        $ex = $pdo->prepare('SELECT 1 FROM students WHERE id = :id');
        $ex->execute([':id' => $id]);
        if (!$ex->fetchColumn()) {
            throw new AppException('الطالب غير موجود.', 404);
        }
        $sql = 'UPDATE students SET name = :n, group_id = :g, score = :s, avatar = :a'
             . ($password !== '' ? ', password_hash = :p' : '') . ' WHERE id = :id';
        $p = [':n' => $name, ':g' => $groupId, ':s' => $score, ':a' => $avatar, ':id' => $id];
        if ($password !== '') {
            $p[':p'] = password_hash($password, PASSWORD_DEFAULT);
        }
        try {
            $pdo->prepare($sql)->execute($p);
        } catch (PDOException $e) {
            throw translate_db_error($e);
        }
        return 'تم تعديل بيانات الطالب بنجاح';
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO students (name, password_hash, group_id, score, avatar)
             VALUES (:n, :p, :g, :s, :a)'
        )->execute([
            ':n' => $name,
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':g' => $groupId,
            ':s' => $score,
            ':a' => $avatar,
        ]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE students SET student_code = :c WHERE id = :id')
            ->execute([':c' => 'STD-' . (100 + $newId), ':id' => $newId]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw translate_db_error($e);
    }
    return 'تمت إضافة الطالب الجديد بنجاح';
}

function translate_db_error(PDOException $e): Throwable
{
    $code = (int)($e->errorInfo[1] ?? 0);
    if ($code === 1062) {
        return new AppException('هذا الاسم مسجل مسبقاً، يرجى اختيار اسم مختلف.');
    }
    if ($code === 1452) {
        return new AppException('المجموعة المحددة غير موجودة.');
    }
    return $e;
}

function delete_student(int $id): string
{
    $st = db()->prepare('DELETE FROM students WHERE id = :id');
    $st->execute([':id' => $id]);
    if ($st->rowCount() === 0) {
        throw new AppException('الطالب غير موجود.', 404);
    }
    return 'تم حذف الطالب من القائمة';
}

/** Atomically change a score by $delta, clamped to 0..100. */
function adjust_score(int $id, $delta): string
{
    $delta = filter_var($delta, FILTER_VALIDATE_INT, ['options' => ['min_range' => -100, 'max_range' => 100]]);
    if ($delta === false || $delta === null) {
        throw new AppException('قيمة التعديل غير صحيحة.');
    }
    $pdo = db();
    $up = $pdo->prepare(
        'UPDATE students SET score = LEAST(100, GREATEST(0, CAST(score AS SIGNED) + :d)) WHERE id = :id'
    );
    $up->bindValue(':d', $delta, PDO::PARAM_INT);
    $up->bindValue(':id', $id, PDO::PARAM_INT);
    $up->execute();

    $st = $pdo->prepare('SELECT name, score FROM students WHERE id = :id');
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        throw new AppException('الطالب غير موجود.', 404);
    }
    return 'تم تحديث تقييم ' . $row['name'] . ' إلى ' . (int)$row['score'] . ' نقطة';
}

function add_group($name): string
{
    $name = clean_text($name);
    $len  = str_chars($name);
    if ($len < 1 || $len > 100) {
        throw new AppException('اسم المجموعة يجب أن يتراوح بين 1 و 100 حرف.');
    }
    try {
        db()->prepare('INSERT INTO `groups` (name) VALUES (:n)')->execute([':n' => $name]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            throw new AppException('هذه المجموعة موجودة مسبقاً.');
        }
        throw $e;
    }
    return 'تمت إضافة مجموعة "' . $name . '"';
}

function delete_group(int $id): string
{
    $pdo = db();
    $g = $pdo->prepare('SELECT name FROM `groups` WHERE id = :id');
    $g->execute([':id' => $id]);
    $name = $g->fetchColumn();
    if ($name === false) {
        throw new AppException('المجموعة غير موجودة.', 404);
    }
    $msg = 'لا يمكن حذف المجموعة (' . $name . ') لأنها تحتوي على طلاب مسجلين حالياً. قم بنقل الطلاب أولاً.';
    $c = $pdo->prepare('SELECT COUNT(*) FROM students WHERE group_id = :id');
    $c->execute([':id' => $id]);
    if ((int)$c->fetchColumn() > 0) {
        throw new AppException($msg);
    }
    try {
        $pdo->prepare('DELETE FROM `groups` WHERE id = :id')->execute([':id' => $id]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1451) { // FK restrict (student added concurrently)
            throw new AppException($msg);
        }
        throw $e;
    }
    return 'تم حذف المجموعة';
}

/** A student's own data + ranking. Never exposes other students. */
function student_profile(int $id): ?array
{
    $pdo = db();
    $st = $pdo->prepare(
        'SELECT s.id, s.student_code, s.name, s.score, s.avatar, s.group_id, g.name AS group_name
         FROM students s JOIN `groups` g ON g.id = s.group_id WHERE s.id = :id LIMIT 1'
    );
    $st->execute([':id' => $id]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $score = (int)$r['score'];

    $q = $pdo->prepare('SELECT COUNT(*) FROM students WHERE score > :s');
    $q->execute([':s' => $score]);
    $rank = 1 + (int)$q->fetchColumn();

    $q = $pdo->prepare('SELECT COUNT(*) FROM students WHERE group_id = :g AND score > :s');
    $q->execute([':g' => (int)$r['group_id'], ':s' => $score]);
    $groupRank = 1 + (int)$q->fetchColumn();

    $total = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();

    $q = $pdo->prepare('SELECT COUNT(*) FROM students WHERE group_id = :g');
    $q->execute([':g' => (int)$r['group_id']]);
    $groupTotal = (int)$q->fetchColumn();

    $avg = (float)$pdo->query('SELECT AVG(score) FROM students')->fetchColumn();

    return [
        'id'          => (int)$r['id'],
        'code'        => (string)$r['student_code'],
        'name'        => $r['name'],
        'score'       => $score,
        'avatar'      => $r['avatar'],
        'group'       => $r['group_name'],
        'rank'        => $rank,
        'total'       => $total,
        'group_rank'  => $groupRank,
        'group_total' => $groupTotal,
        'avg'         => round($avg, 1),
    ];
}
