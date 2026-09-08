<?php
declare(strict_types=1);

final class AmbassadorProfiles
{
    private const ENTRY_YEAR_BY_STUDY_YEAR = [1 => '25', 2 => '24', 3 => '23', 4 => '22'];

    public static function studentCodePrefix(string $major): string
    {
        $major = mb_strtolower(trim($major), 'UTF-8');
        if (str_contains($major, 'ngôn ngữ hàn')) return 'BKL';
        if (str_contains($major, 'ngôn ngữ nhật')) return 'BJL';
        if (str_contains($major, 'thiết kế đồ họa') || str_contains($major, 'thiết kế đồ hoạ')) return 'BGD';
        if (str_contains($major, 'khoa học máy tính')) return 'BCS';
        if (str_contains($major, 'công nghệ thông tin') || str_contains($major, 'cntt')) return 'BIT';
        foreach (['kinh tế', 'kinh doanh', 'quản trị', 'marketing', 'kế toán', 'tài chính'] as $businessMajor) {
            if (str_contains($major, $businessMajor)) return 'BBA';
        }
        throw new InvalidArgumentException('Chưa có nhóm mã sinh viên cho ngành: '.$major);
    }

    public static function entryYearForStudyYear(int $studyYear): string
    {
        return self::ENTRY_YEAR_BY_STUDY_YEAR[$studyYear]
            ?? throw new InvalidArgumentException('Năm học phải nằm trong khoảng 1-4.');
    }

    public static function isValidStudentCode(string $code, string $major, int $studyYear): bool
    {
        $stem = self::studentCodePrefix($major).self::entryYearForStudyYear($studyYear);
        return preg_match('/^'.preg_quote($stem, '/').'1\d{3}$/D', $code) === 1;
    }

    public static function samples(): array
    {
        $profiles=json_decode(file_get_contents(__DIR__.'/../data/ambassador-samples.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($profiles as $i=>&$p) {
            $suffix=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);
            $p['number']=$i+1; $p['key']='campus-profile-'.$suffix;
            $p['avatar']='assets/img/ambassadors/portrait-'.$suffix.'.png';
            $p['bio']=explode('. ', $p['about'])[0].'.';
            if (!self::isValidStudentCode((string)($p['student_code'] ?? ''), (string)$p['major'], (int)$p['year'])) {
                throw new RuntimeException('Mã sinh viên không khớp ngành hoặc năm học: '.$p['name']);
            }
        }
        unset($p); return $profiles;
    }
    public static function migrate(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS ambassador_profiles (
            user_id INTEGER PRIMARY KEY REFERENCES users(id),
            about TEXT NOT NULL DEFAULT '', topics TEXT NOT NULL DEFAULT '',
            activities TEXT NOT NULL DEFAULT '', projects TEXT NOT NULL DEFAULT '',
            languages TEXT NOT NULL DEFAULT '', advice TEXT NOT NULL DEFAULT '',
            sample_key TEXT UNIQUE, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        self::syncBundledStudentCodes($db);
    }

    public static function details(PDO $db, int $id): array
    {
        $q=$db->prepare('SELECT about,topics,activities,projects,languages,advice FROM ambassador_profiles WHERE user_id=?');
        $q->execute([$id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Install the versioned public fixture once; never restore later admin deletions. */
    public static function installBundledProfiles(PDO $db): int
    {
        $db->exec('CREATE TABLE IF NOT EXISTS ambassador_fixture_versions (version TEXT PRIMARY KEY, installed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        if ($db->query("SELECT 1 FROM ambassador_fixture_versions WHERE version='profiles-v1'")->fetchColumn()) return 0;
        $profiles = self::samples();
        foreach ($profiles as $profile) {
            if (!is_file(__DIR__.'/../'.$profile['avatar'])) throw new RuntimeException('Missing bundled portrait: '.$profile['avatar']);
        }
        return self::seed($db, $profiles, true);
    }

    public static function directory(PDO $db): array
    {
        return $db->query("SELECT u.id,u.name,u.major,u.hometown,u.interests,u.bio,u.avatar,u.study_year,u.is_online,
            p.about,p.topics,p.activities,p.projects,p.languages,p.advice
            FROM eligible_ambassadors u LEFT JOIN ambassador_profiles p ON p.user_id=u.id
            ORDER BY u.is_online DESC,u.name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function avatar(?string $path): string
    {
        return is_string($path) && preg_match('~^assets/img/[a-zA-Z0-9/_-]+\.(?:jpg|jpeg|png|webp)$~D',$path) ? $path : '';
    }

    public static function avatarHtml(array $person): string
    {
        $path=self::avatar($person['avatar']??'');
        return $path ? '<img src="'.e($path).'" alt="" width="96" height="96" loading="lazy">' : e(initials($person['name']));
    }

    public static function seed(PDO $db, array $profiles, bool $recordInstallation = false): int
    {
        $count=0; $db->beginTransaction();
        try {
            foreach ($profiles as $profile) {
                $key=$profile['key'];
                $q=$db->prepare('SELECT 1 FROM ambassador_profiles WHERE sample_key=?');$q->execute([$key]);
                if($q->fetchColumn()) continue;
                $email=$key.'@ambassadors.example.invalid';
                $q=$db->prepare('SELECT 1 FROM users WHERE email=?');$q->execute([$email]);
                if($q->fetchColumn()) throw new RuntimeException('Sample email collision: '.$key);
                $db->prepare("INSERT INTO users(role,name,email,password,student_code,major,hometown,interests,bio,avatar,study_year,status,is_online,policy_status) VALUES('ambassador',?,?,?,?,?,?,?,?,?,?,'active',0,'approved')")
                    ->execute([$profile['name'],$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$profile['student_code'],$profile['major'],$profile['hometown'],$profile['interests'],$profile['bio'],$profile['avatar'],$profile['year']]);
                $id=(int)$db->lastInsertId();
                $db->prepare('INSERT INTO ambassador_profiles(user_id,about,topics,activities,projects,languages,advice,sample_key) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$profile['about'],$profile['topics'],$profile['activities'],$profile['projects'],$profile['languages'],$profile['advice'],$key]);
                $count++;
            }
            if ($recordInstallation) $db->exec("INSERT OR IGNORE INTO ambassador_fixture_versions(version) VALUES('profiles-v1')");
            $db->commit();return $count;
        } catch(Throwable $e) {$db->rollBack();throw $e;}
    }

    /** Upgrade only bundled legacy DS codes; preserve codes edited by an administrator. */
    private static function syncBundledStudentCodes(PDO $db): void
    {
        $update=$db->prepare("UPDATE users SET student_code=? WHERE id=(SELECT user_id FROM ambassador_profiles WHERE sample_key=?) AND student_code LIKE 'DS%'");
        foreach (self::samples() as $profile) $update->execute([$profile['student_code'],$profile['key']]);
    }
}
