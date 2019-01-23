<?php
namespace common\widgets;

/* Opcodes */
define("WS_FRAME_INVALID", -1);
define("WS_FRAME_CONT", 0x0);
define("WS_FRAME_TEXT", 0x1);
define("WS_FRAME_BINARY", 0x2);
define("WS_FRAME_03", 0x3);
define("WS_FRAME_04", 0x4);
define("WS_FRAME_05", 0x5);
define("WS_FRAME_06", 0x6);
define("WS_FRAME_07", 0x7);
define("WS_FRAME_CLOSE", 0x8);
define("WS_FRAME_PING", 0x9);
define("WS_FRAME_PONG", 0xA);
define("WS_FRAME_0B", 0xB);
define("WS_FRAME_0C", 0xC);
define("WS_FRAME_0D", 0xD);
define("WS_FRAME_0E", 0xE);
define("WS_FRAME_0F", 0xF);

/* Frame decoding states */
define("FRAME_STATE_ERROR", -1);
define("FRAME_STATE_BEGIN", 0);
define("FRAME_STATE_LEN", 1);
define("FRAME_STATE_KEY", 2);
define("FRAME_STATE_PAYLOAD", 3);
define("FRAME_STATE_COMPLETED", 4);

/* Frame error codes */
define("FRAME_ERROR_NONE", 0);
define("FRAME_ERROR_INTERNAL", 1);
define("FRAME_ERROR_TOO_LARGE", 2);

/**
 * Websocket Frame
 */
class WSFrame
{
    public $fin;
    public $rsv;
    public $opcode;
    public $masked;
    public $length;
    public $masking_key;
    public $payload;

    public $errcode;
    public $framestate;

    private $framepos;
    private $lenlen;
    private $lenbuf;

    function __construct()
    {
        $this->clear();
    }

    /*
     * Clear object
     * name: WSFrame::clear
     *
     */
    function clear()
    {
        $this->fin = 0;
        $this->rsv = 0;
        $this->opcode = WS_FRAME_INVALID;
        $this->masked = 0;
        $this->length = 0;
        $this->masking_key = array();
        $this->payload = "";
        $this->errcode = FRAME_ERROR_NONE;
        $this->framepos = 0;
        $this->framestate = FRAME_STATE_BEGIN;
        $this->lenlen = 0;
        $this->lenbuf = array();
    }

    /*
     * Create simple frame
     * name: WSFrame::set
     * @param int $opcode
     * @param string $payload
     * @param int $masked
     * @param array $masking_key
     *
     */
    function set($opcode, $payload, $masked = 0, $masking_key = array())
    {
        $this->fin = 1;
        $this->payload = $payload;
        $this->length = strlen($payload);
        $this->opcode = $opcode;
        $this->masked = $masked;
        if ($masked) {
            if (count($masking_key) == 4) {
                $this->masking_key = $masking_key;
            } else {
                $this->genkey();
            }
        }
        $this->framestate = FRAME_STATE_COMPLETED;
    }

    /*
     * Generate random masking key
     * name: WSFrame::genkey
     *
     */
    function genkey()
    {
        mt_srand();
        $this->masking_key = array(
            mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)
        );
    }

    /*
     * Decode portion of data
     * name: WSFrame::decode
     * @param string $data
     * @return int
     *
     */
    function decode($data)
    {
        $datalen = strlen($data);
        $datapos = 0;
        while ($datapos < $datalen)
        {
            if (($this->framestate == FRAME_STATE_ERROR) || ($this->framestate == FRAME_STATE_COMPLETED)) return $datapos;

            $byte = ord($data[$datapos]);
            switch ($this->framestate)
            {
                case FRAME_STATE_BEGIN:
                    if ($this->framepos == 0) {
                        // Process first byte of frame
                        $this->fin = ($byte >> 7) & 0x01;
                        $this->rsv = ($byte >> 4) & 0x07;
                        $this->opcode = $byte & 0x0F;
                    } elseif ($this->framepos == 1) {
                        // Process second byte of frame
                        $this->masked = ($byte >> 7) & 0x01;
                        $pl = $byte & 0x7F;
                        switch($pl) {
                            case 126:
                                $this->lenlen = 2;
                                $this->framestate = FRAME_STATE_LEN;
                                break;
                            case 127:
                                $this->lenlen = 8;
                                $this->framestate = FRAME_STATE_LEN;
                                break;
                            default:
                                $this->lenlen = 0;
                                $this->length = $pl;
                                $this->framestate = $pl ? ($this->masked ? FRAME_STATE_KEY : FRAME_STATE_PAYLOAD) : FRAME_STATE_COMPLETED;
                        }
                    } else {
                        $this->framestate = FRAME_STATE_ERROR;
                        $this->errcode = FRAME_ERROR_INTERNAL;
                    }
                    $this->framepos++;
                    break;
                case FRAME_STATE_LEN:
                    if (($this->framepos >=2) && ($this->framepos < (2 + $this->lenlen))) {
                        $this->lenbuf[] = $byte;
                        if (count($this->lenbuf) == $this->lenlen) {
                            for ($i = $this->lenlen - 1; $i >= 0; $i--) {
                                //TODO: Add overflow handling for <64 bit OS
                                $this->length |= $this->lenbuf[$i] << (8 * ($this->lenlen - $i - 1));
                            }
                            $this->framestate = $this->masked ? FRAME_STATE_KEY : FRAME_STATE_PAYLOAD;
                        }
                    } else {
                        $this->framestate = FRAME_STATE_ERROR;
                        $this->errcode = FRAME_ERROR_INTERNAL;
                    }
                    $this->framepos++;
                    break;
                case FRAME_STATE_KEY:
                    if (($this->framepos >= (2 + $this->lenlen)) && ($this->framepos < (6 + $this->lenlen))) {
                        $this->masking_key[] = $byte;
                        if (count($this->masking_key) == 4) {
                            $this->framestate = $this->length ? FRAME_STATE_PAYLOAD : FRAME_STATE_COMPLETED; // empty masked frame?
                        }
                    } else {
                        $this->framestate = FRAME_STATE_ERROR;
                        $this->errcode = FRAME_ERROR_INTERNAL;
                    }
                    break;
                case FRAME_STATE_PAYLOAD:
                    $pl = strlen($this->payload);
                    if ($pl < $this->length) {
                        if ($this->masked) {
                            $this->payload .= chr($byte ^ $this->masking_key[$pl % 4]);
                        } else {
                            $this->payload .= chr($byte);
                        }
                        if (++$pl == $this->length) $this->framestate = FRAME_STATE_COMPLETED;
                    }
                    $this->framepos++;
                    break;
                case FRAME_STATE_COMPLETED:
                    return $datapos;
                default:
            }
            $datapos++;
        }
        return $datapos;
    }

    /*
     * Is frame completed?
     * name: WSFrame::completed
     * @return boolean
     *
     */
    function completed()
    {
        return ($this->framestate == FRAME_STATE_COMPLETED);
    }

    /*
     * Encode frame
     * name: WSFrame::encode
     * @return string
     *
     */
    function encode()
    {
        $retval = chr((($this->fin & 0x1) << 7) | (($this->rsv & 0x7) << 4) | ($this->opcode & 0xF));

        $pl = strlen($this->payload);
        if ($pl < 126) {
            $retval .= chr(($pl & 0x7F) | (($this->masked & 0x1) << 7));
        } elseif ($pl <= 0xFFFF) {
            $retval .= chr(126 | (($this->masked & 0x1) << 7));
            $retval .= pack('n', $pl);
        } else {
            $retval .= chr(127 | (($this->masked & 0x1) << 7));
            for ($i = 7; $i >=0; $i--) {
                $retval .= chr(($pl >> (8 * $i)) & 0xFF);
            }
        }

        if ($this->masked) {
            $retval .= chr($this->masking_key[0]) . chr($this->masking_key[1]) .
                chr($this->masking_key[2]) . chr($this->masking_key[3]);
            for ($i = 0; $i < $pl; $i++) {
                $retval .= chr(ord($this->payload[$i]) ^ $this->masking_key[$i % 4]);
            }
        } else {
            $retval .= $this->payload;
        }

        return $retval;
    }
}
