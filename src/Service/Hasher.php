<?php

namespace S3Files\Service;


class Hasher
{
    protected $secret;
    protected $offset;

    public function __construct($secret, $offset)
    {
        $this->secret = $secret;
        $this->offset = intval($offset);
    }

    public function makeHashPacket($id)
    {
        $hmac = hash_hmac('md5', $id, $this->secret);
        $offsetId = $id + $this->offset;
        $obscuredId = base_convert($offsetId, 10, 36);

        return "{$obscuredId}:{$hmac}";

    }

    public function readHashPacket($packet)
    {
        $pieces = explode(':', $packet);
        if (count($pieces) != 2) {
            throw new \Exception('Invalid hash packet');
        }

        $obscuredId = $pieces[0];
        $hmac = $pieces[1];

        $offsetId = base_convert($obscuredId, 36, 10);
        if (empty($offsetId)) {
            throw new \Exception('Invalid hash packet');
        }
        $id = $offsetId - $this->offset;

        $testHmac = hash_hmac('md5', $id, $this->secret);
        if ($testHmac !== $hmac) {
            throw new \Exception('Invalid hash packet');
        }

        return intval($id);
    }
}
