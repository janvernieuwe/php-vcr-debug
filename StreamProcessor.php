<?php

require_once __DIR__.'/SampleFilter.php';

/**
 * Implementation adapted from:
 * https://github.com/antecedent/patchwork/blob/418a9aae80ca3228d6763a2dc6d9a30ade7a4e7e/lib/Preprocessor/Stream.php
 *
 * @author     Ignas Rudaitis <ignas.rudaitis@gmail.com>
 * @author     Adrian Philipp <mail@adrian-philipp.com>
 * @copyright  2010-2013 Ignas Rudaitis
 * @license    http://www.opensource.org/licenses/mit-license.html
 * @link       http://antecedent.github.com/patchwork
 */
class StreamProcessor
{
    /**
     * Constant for a stream which was opened while including a file.
     */
    const STREAM_OPEN_FOR_INCLUDE = 128;

    /**
     * Stream protocol which is used when registering this wrapper.
     */
    const PROTOCOL = 'file';

    /**
     * @var AbstractCodeTransform[] $codeTransformers Transformers which have been appended to this stream processor.
     */
    protected static $codeTransformers = array();
    /**
     * @link http://www.php.net/manual/en/class.streamwrapper.php#streamwrapper.props.context
     * @var resource The current context, or NULL if no context was passed to the caller function.
     */
    public $context;
    /**
     * @var resource Resource for the currently opened file.
     */
    protected $resource;

    /**
     * Opens a stream and attaches registered filters.
     *
     * @param string  $path Specifies the URL that was passed to the original function.
     * @param string  $mode The mode used to open the file, as detailed for fopen().
     * @param integer $options Holds additional flags set by the streams API.
     *                             It can hold one or more of the following values OR'd together.
     * @param string  $openedPath If the path is opened successfully, and STREAM_USE_PATH is set in options,
     *                             opened_path should be set to the full path of the file/resource that was
     *                             actually opened.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function stream_open($path, $mode, $options, &$openedPath)
    {
        // file_exists catches paths like /dev/urandom that are missed by is_file.
        if ('r' === substr($mode, 0, 1) && !file_exists($path)) {
            return false;
        }

        $this->restore();

        if (isset($this->context)) {
            $this->resource = fopen($path, $mode, $options & STREAM_USE_PATH, $this->context);
        } else {
            $this->resource = fopen($path, $mode, $options & STREAM_USE_PATH);
        }

        if ($options & self::STREAM_OPEN_FOR_INCLUDE) {
            $this->appendFiltersToStream($this->resource);
        }

        $this->intercept();

        return $this->resource !== false;
    }

    /**
     * Restores the original file stream wrapper status.
     *
     * @return void
     */
    public function restore()
    {
        stream_wrapper_restore(self::PROTOCOL);
    }

    /**
     * Appends the current set of php_user_filter to the provided stream.
     *
     * @param resource $stream
     */
    protected function appendFiltersToStream($stream)
    {
        foreach (static::$codeTransformers as $codeTransformer) {
            stream_filter_append($stream, $codeTransformer::NAME, STREAM_FILTER_READ);
        }
    }

    /**
     * Registers current class as the PHP file stream wrapper.
     *
     * @return void
     */
    public function intercept()
    {
        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_register(self::PROTOCOL, __CLASS__);
    }


    /**
     * Tests for end-of-file on a file pointer.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-eof.php
     *
     * @return boolean Should return TRUE if the read/write position is at the end of the stream
     *                 and if no more data is available to be read, or FALSE otherwise.
     */
    public function stream_eof()
    {
        return feof($this->resource);
    }


    /**
     * Read from stream.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-read.php
     *
     * @param int $count How many bytes of data from the current position should be returned.
     *
     * @return string If there are less than count bytes available, return as many as are available.
     *                If no more data is available, return either FALSE or an empty string.
     */
    public function stream_read($count)
    {
        return fread($this->resource, $count);
    }


    /**
     * Retrieve information about a file resource.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-stat.php
     *
     * @return array See stat().
     */
    public function stream_stat()
    {
        return fstat($this->resource);
    }

    /**
     * Retrieve information about a file.
     *
     * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
     *
     * @param string  $path The file path or URL to stat.
     * @param integer $flags Holds additional flags set by the streams API.
     *
     * @return integer        Should return as many elements as stat() does.
     */
    public function url_stat($path, $flags)
    {
        $this->restore();
        if ($flags & STREAM_URL_STAT_QUIET) {
            set_error_handler(
                function () {
                    // Use native error handler
                    return false;
                }
            );
            $result = @stat($path);
            restore_error_handler();
        } else {
            $result = stat($path);
        }
        $this->intercept();

        return $result;
    }

    /**
     * Change stream options.
     *
     * @codeCoverageIgnore
     *
     * @param int $option One of STREAM_OPTION_BLOCKING, STREAM_OPTION_READ_TIMEOUT, STREAM_OPTION_WRITE_BUFFER.
     * @param int $arg1 Depending on option.
     * @param int $arg2 Depending on option.
     *
     * @return boolean Returns TRUE on success or FALSE on failure. If option is not implemented,
     *                 FALSE should be returned.
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                return stream_set_blocking($this->resource, $arg1);
            case STREAM_OPTION_READ_TIMEOUT:
                return stream_set_timeout($this->resource, $arg1, $arg2);
            case STREAM_OPTION_WRITE_BUFFER:
                return stream_set_write_buffer($this->resource, $arg1);
            case STREAM_OPTION_READ_BUFFER:
                return stream_set_read_buffer($this->resource, $arg1);
            case STREAM_OPTION_CHUNK_SIZE:
                return stream_set_chunk_size($this->resource, $arg1);
        }
    }


    /**
     * Adds code transformer to the stream processor.
     *
     * @param  $codeTransformer
     *
     * @return void
     */
    public function appendCodeTransformer($codeTransformer)
    {
        static::$codeTransformers[$codeTransformer::NAME] = $codeTransformer;
    }
}
