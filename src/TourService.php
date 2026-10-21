<?php
declare(strict_types=1);

/** Tour creation, editing and publication; bookings retain their own lifecycle. */
final class TourService
{
    public function __construct(private PDO $pdo, private TourInventory $inventory,
        private AccessControl $access, private Outbox $outbox) {}

    public function saveTour(int $actor, array $values, ?int $id = null): int
    {
        $this->access->require($actor, 'tours');
        TourRules::validate($values);
        $fields = TourRules::FIELDS;
        return Database::atomic($this->pdo, function () use ($actor, $values, $id, $fields) {
            if ($values['category_id'] !== null && !Database::run($this->pdo, 'SELECT id FROM Topics WHERE id=? FOR UPDATE', [$values['category_id']])->fetchColumn()) {
                throw new RuleViolation('دسته انتخاب‌شده وجود ندارد.');
            }
            if ($id !== null) {
                $previous = $this->inventory->tour($id, true);
                if (isset($values['expected_version']) && (int)$values['expected_version'] !== (int)$previous['version']) {
                    throw new RuleViolation('تور توسط مدیر دیگری تغییر کرده است؛ ویرایش را از ابتدا باز کنید.');
                }
                if ($values['capacity'] < $this->inventory->used($id)) {
                    throw new RuleViolation('ظرفیت جدید از تعداد صندلی‌های رزروشده کمتر است.');
                }
                Database::run($this->pdo, 'UPDATE Tours SET ' . implode(',', array_map(fn($f) => "$f=?", $fields)) . ',version=version+1 WHERE id=?', array_merge(array_map(fn($f) => $values[$f], $fields), [$id]));
                $users = Database::run($this->pdo, "SELECT user_id FROM Bookings WHERE tour_id=? AND user_id IS NOT NULL AND status IN ('pending','confirmed')", [$id])->fetchAll(PDO::FETCH_COLUMN);
                foreach ($users as $user) {
                    $this->outbox->message((int)$user, "مشخصات تور شماره $id تغییر کرد. لطفاً جزئیات جدید را از فهرست تورها ببینید.", "tour:$id:version:" . ($previous['version'] + 1) . ":user:$user");
                }
            }
            else {
                Database::run($this->pdo, 'INSERT INTO Tours(' . implode(',', $fields) . ') VALUES (?,?,?,?,?,?,?,?,?)', array_map(fn($f) => $values[$f], $fields));
                $id = (int)$this->pdo->lastInsertId();
            }
            $this->access->audit($actor, 'tour.save', 'tour', $id);
            return $id;
        }
        );
    }
    public function publication(int $actor,int $id,string $publication):int
    {
        $this->access->require($actor,'tours');
        if(!in_array($publication,['draft','published','inactive','archived'],true))throw new RuleViolation('وضعیت انتشار معتبر نیست.');
        return Database::atomic($this->pdo,function()use($actor,$id,$publication){
            // Read current locked values; a stale UI snapshot must not undo another edit.
            $tour=$this->inventory->tour($id,true);
            if($tour['publication']===$publication)return$id;
            $tour['price']=(int)$tour['price'];$tour['capacity']=(int)$tour['capacity'];
            $tour['publication']=$publication;$tour['expected_version']=(int)$tour['version'];
            $this->saveTour($actor,$tour,$id);
            $this->access->audit($actor,'tour.publication.'.$publication,'tour',$id);
            return$id;
        }
        );
    }
}
