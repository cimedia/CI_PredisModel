<?php
class PredisModel extends Base_model
{
    use IdeFriendlyTrait;
    public $client;

    const PREDIS_DBS = [
        'OFFERS' => 1,
        'USERS' => 2
    ];

    /**
     * PredisModel constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $hosts = ENVIRONMENT == 'prod' ? ['x.x.x.x'] : ['x.x.x.x'];
        $port = '6379';
        $password = ENVIRONMENT == 'prod' ? 'xxx' : 'xxx';

        $connectionParameters = [];
        foreach ($hosts as $host) {
            $connectionParameters = [
                'host' => $host,
                'port' => $port,
                'password' => $password
            ];
        }

        $this->load->model('log_model');

        try {
            $this->client = new Predis\Client($connectionParameters);
        } catch (Exception $e) {
            $this->log_model->save($this->prepareLogData($e));
        }
    }

    /**
     * @param string $key
     * @param stdClass $value
     * @param string $predisDb
     * @return null|\Predis\Response\Status
     */
    public function setPredisData(string $key, stdClass $value, string $predisDb):? Predis\Response\Status
    {
        try {
            if ($this->verifyPredisDb($predisDb)) {
                $this->selectPredisDatabase($predisDb);
                $result = $this->client->set($key, serialize($value));
            } else {
                throw new Exception('Wrong database selected');
            }
        } catch (Exception $e) {
            $this->log_model->save($this->prepareLogData($e));
            $result = null;
        }
        return $result;
    }

    /**
     * @param string $key
     * @param string $predisDb
     * @return null|stdClass
     */
    public function getPredisData(string $key, string $predisDb):? stdClass
    {
        try {
            if ($this->verifyPredisDb($predisDb)) {
                $this->selectPredisDatabase($predisDb);
                $result = $this->client->get($key);
                if (!empty($result)) {
                    $result = unserialize($result);
                }
            } else {
                throw new Exception('Wrong database selected');
            }
        } catch (Exception $e) {
            $this->log_model->save($this->prepareLogData($e));
            $result = null;
        }
        return $result;
    }

    /**
     * @param string $key
     * @param string $predisDb
     * @return int|null
     */
    public function deletePredisData(string $key, string $predisDb):? int
    {
        try {
            if ($this->verifyPredisDb($predisDb)) {
                $this->selectPredisDatabase($predisDb);
                $result = $this->client->del([$key]);
            } else {
                throw new Exception('Wrong database selected');
            }
        } catch (Exception $e) {
            $this->log_model->save($this->prepareLogData($e));
            $result = null;
        }
        return $result;
    }

    /**
     * @param string $dbKey
     */
    private function selectPredisDatabase(string $dbKey): void
    {
        try {
            $this->client->select(self::PREDIS_DBS[$dbKey]);
        } catch (Exception $e) {
            $this->log_model->save($this->prepareLogData($e));
        }
    }

    /**
     * @param $predisDb
     * @return bool
     */
    private function verifyPredisDb($predisDb): bool
    {
        return !empty($predisDb) and array_key_exists($predisDb, self::PREDIS_DBS);
    }

    /**
     * @param Exception $exc
     * @return array
     */
    private function prepareLogData(Exception $exc): array
    {
        return [
            'message' => $exc->getMessage(),
            'type' => 'EXCEPTION',
            'operationKey' => 'PREDIS_CLIENT',
            'data' => $exc->getFile() . ' in line: ' . $exc->getLine() . ', trace: ' . json_encode($exc->getTrace())
        ];
    }
}
