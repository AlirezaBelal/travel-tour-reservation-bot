<?php
declare(strict_types=1);

/** Self-service profile operations and separately authorized administrator views. */
final class TravelerProfileService
{
    public function __construct(
        private PDO $pdo,
        private TravelerIdentity $identity,
        private PrivacyService $privacy,
        private AccessControl $access
    ) {}

    public function legacyStep(int $actor): ?string
    {
        $step = Database::run($this->pdo, 'SELECT step FROM Data WHERE UserId=?', [$actor])->fetchColumn();
        return $step === false || $step === null ? null : (string)$step;
    }

    public function resetLegacyState(int $actor): void
    {
        Database::run($this->pdo, "UPDATE Data SET step='defult' WHERE UserId=?", [$actor]);
    }

    public function name(int $actor): ?string
    {
        $name = Database::run($this->pdo, 'SELECT Name FROM Data WHERE UserId=?', [$actor])->fetchColumn();
        return $name === false || $name === null ? null : (string)$name;
    }

    public function leaderForReview(int $actor, int $subject): ?string
    {
        $this->access->require($actor, 'review');
        $this->access->require($actor, 'profiles');
        $name = $this->name($subject);
        $this->access->audit($actor, 'profile.view', 'user', $subject);
        return $name;
    }

    /** @return array{mobile: bool, national: bool} */
    public function status(int $actor): array
    {
        return $this->identity->status($actor);
    }

    public function saveName(int $actor, string $name): void
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 150) {
            throw new RuleViolation('نام باید کوتاه و غیرخالی باشد.');
        }
        Database::run($this->pdo, "UPDATE Data SET Name=?,profile='true' WHERE UserId=?", [$name, $actor]);
    }

    public function saveMobile(int $actor, array $contact): void
    {
        $this->identity->saveMobile($actor, $contact);
    }

    public function saveNational(int $actor, string $text): void
    {
        $this->identity->saveNational($actor, $text);
    }

    public function erase(int $actor): void
    {
        $this->privacy->erase($actor);
    }
}
