<?php
declare(strict_types=1);

namespace Magpie\Controllers;

use PDO;

abstract class BaseController {
    protected PDO $db;

    public function __construct() {
        global $db;
        $this->db = $db;
    }

    protected function getPayload(): array {
        return get_json_payload();
    }
}
