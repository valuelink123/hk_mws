<?php
namespace App\Services\Traits;


use Yadakhov\InsertOnDuplicateKey;

trait ExtendedMysqlQueries
{
    use InsertOnDuplicateKey;

    /**
     * @param string $method
     */
    public static function insertOnDuplicateWithDeadlockCatching(array $data, array $updateColumns = null)
    {
        l1:
        try {
            static::insertOnDuplicateKey($data, $updateColumns);
        }
        catch (\Illuminate\Database\QueryException $e) {
            $errorCode = $e->errorInfo[1];
            //if "Deadlock found when trying to get lock"
            if ($errorCode == 1213) {
                sleep(1);
                goto l1;

            } else
                throw($e);

        }
    }
}