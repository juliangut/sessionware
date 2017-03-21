<?php

/*
 * sessionware (https://github.com/juliangut/sessionware).
 * PSR7 compatible session management.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/sessionware
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\Sessionware;

/**
 * Data collection.
 */
class Collection
{
    /**
     * Collection data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Collection constructor.
     *
     * @param array $initialData
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $initialData = [])
    {
        foreach ($initialData as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Collection data existence.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get all data.
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->data;
    }

    /**
     * Retrieve collection data.
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * Set collection data.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws \InvalidArgumentException
     */
    public function set(string $key, $value)
    {
        $this->verifyScalarValue($value);

        $this->data[$key] = $value;
    }

    /**
     * Verify only scalar values allowed.
     *
     * @param string|int|float|bool|array $value
     *
     * @throws \InvalidArgumentException
     */
    final protected function verifyScalarValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                $this->verifyScalarValue($val);
            }
        }

        if (!is_scalar($value)) {
            throw new \InvalidArgumentException(sprintf('Session values must be scalars, %s given', gettype($value)));
        }
    }

    /**
     * Remove collection data.
     *
     * @param string $key
     */
    public function remove(string $key)
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
        }
    }

    /**
     * Remove all collection data.
     */
    public function clear()
    {
        $this->data = [];
    }
}
