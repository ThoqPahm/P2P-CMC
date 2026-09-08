<?php
declare(strict_types=1);

final class AmbassadorProfiles
{
    public static function samples(): array
    {
        $profiles=json_decode(file_get_contents(__DIR__.'/../data/ambassador-samples.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($profiles as $i=>&$p) {
            $suffix=str_pad((string)($i+1),2,'0',STR_PAD_LEFT);
            $p['number']=$i+1; $p['key']='campus-profile-'.$suffix;
            $p['avatar']='assets/img/ambassadors/portrait-'.$suffix.'.png';
            $p['bio']=explode('. ', $p['about'])[0].'.';
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
                    ->execute([$profile['name'],$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'DS'.str_pad((string)$profile['number'],4,'0',STR_PAD_LEFT),$profile['major'],$profile['hometown'],$profile['interests'],$profile['bio'],$profile['avatar'],$profile['year']]);
                $id=(int)$db->lastInsertId();
                $db->prepare('INSERT INTO ambassador_profiles(user_id,about,topics,activities,projects,languages,advice,sample_key) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$profile['about'],$profile['topics'],$profile['activities'],$profile['projects'],$profile['languages'],$profile['advice'],$key]);
                $count++;
            }
            if ($recordInstallation) $db->exec("INSERT OR IGNORE INTO ambassador_fixture_versions(version) VALUES('profiles-v1')");
            $db->commit();return $count;
        } catch(Throwable $e) {$db->rollBack();throw $e;}
    }
}
