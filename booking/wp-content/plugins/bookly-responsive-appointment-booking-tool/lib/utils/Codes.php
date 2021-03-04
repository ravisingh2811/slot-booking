<?php
namespace Bookly\Lib\Utils;

/**
 * Class Codes
 * @package Bookly\Lib\Utils
 */
abstract class Codes
{
    protected static $tokens = array(
        'T_CODE' => '{(\w+(?:\.\w+)*)}',
        'T_IF' => '{#if\s+(\w+(?:\.\w+)*)\s*}\n?',
        'T_END_IF' => '{\/if}\n?',
        'T_EACH' => '{#each\s+(\w+(?:\.\w+)*)\s+as\s+(\w+)(?:\s+delimited\s+by\s+"(.+?)")?\s*}\n?',
        'T_END_EACH' => '{\/each}\n?',
    );

    /**
     * Replace codes in text
     *
     * @param string $text
     * @param array $codes
     * @param bool $bold
     * @return string
     */
    public static function replace( $text, $codes, $bold = true )
    {
        return self::stringify( self::tokenize( $text ), $codes, $bold );
    }

    /**
     * Build string from tokens and codes data
     *
     * @param array $tokens
     * @param array $codes
     * @param bool $bold
     * @return string
     */
    protected static function stringify( $tokens, $codes, $bold )
    {
        $output = '';

        foreach ( $tokens as $token ) {
            switch ( $token[0] ) {
                case 'T_TEXT':
                    $output .= $token[1];
                    break;
                case 'T_CODE':
                    $data = self::get( $token[1], $codes );
                    if ( $data !== null ) {
                        if ( $bold ) {
                            $output .= '<b>' . $data . '</b>';
                        } else {
                            $output .= $data;
                        }
                    }
                    break;
                case 'T_IF':
                    $data = self::get( $token[1], $codes );
                    $nested_tokens = $token[2];
                    if ( ! empty ( $data ) ) {
                        $output .= self::stringify( $nested_tokens, $codes, $bold );
                    }
                    break;
                case 'T_EACH':
                    $data = self::get( $token[1], $codes );
                    $context_code = $token[2];
                    $delimiter = $token[3];
                    $nested_tokens = $token[4];
                    if ( is_array( $data ) ) {
                        $parts = array();
                        foreach ( $data as $context_codes ) {
                            $parts[] = self::stringify( $nested_tokens, array( $context_code => $context_codes ) + $codes, $bold );
                        }
                        $output .= implode( $delimiter, $parts );
                    }
                    break;
                default:
                    // Do nothing
            }
        }

        return $output;
    }

    /**
     * Split text into array of tokens
     *
     * @param string $text
     * @param int $offset
     * @param string $stop_token
     * @return array
     */
    protected static function tokenize( $text, &$offset = 0, $stop_token = null )
    {
        $tokens = array();
        $text_start = null;

        while ( isset ( $text[ $offset ] ) ) {
            if ( $type = self::match( $text, $matches, $offset ) ) {
                if ( $text_start !== null ) {
                    // Raw text ended
                    $tokens[] = array( 'T_TEXT', substr( $text, $text_start, $offset - $text_start ) );
                    $text_start = null;
                }

                $offset += strlen( $matches[0] );

                if ( $type == $stop_token ) {
                    break;
                }

                $token = array( $type );

                if ( $type == 'T_CODE' ) {
                    $token[] = $matches[1];
                } elseif ( $type == 'T_IF' ) {
                    $token[] = $matches[1];
                    $token[] = self::tokenize( $text, $offset, 'T_END_IF' );
                } elseif ( $type == 'T_EACH' ) {
                    $token[] = $matches[1]; // code
                    $token[] = $matches[2]; // context code
                    $token[] = isset ( $matches[3] ) ? $matches[3] : ''; // delimiter
                    $token[] = self::tokenize( $text, $offset, 'T_END_EACH' );
                }

                $tokens[] = $token;
            } else {
                if ( $text_start === null ) {
                    // Raw text started
                    $text_start = $offset;
                }
                ++ $offset;
            }
        }
        if ( $text_start !== null ) {
            // Raw text ended
            $tokens[] = array( 'T_TEXT', substr( $text, $text_start ) );
        }

        return $tokens;
    }

    /**
     * Match string with tokens
     *
     * @param string $subject
     * @param array &$matches
     * @param int $offset
     * @return false|string
     */
    protected static function match( $subject, &$matches, $offset )
    {
        foreach ( self::$tokens as $type => $pattern ) {
            if ( preg_match( "/$pattern/A", $subject, $matches, null, $offset ) ) {

                return $type;
            }
        }

        return false;
    }

    /**
     * Get dot-notated path from array
     *
     * @param string $path
     * @param array $array
     * @return mixed|null
     */
    protected static function get( $path, $array )
    {
        $result = $array;
        foreach ( explode( '.', $path ) as $key ) {
            if ( isset ( $result[ $key ] ) ) {
                $result = $result[ $key ];
            } else {
                return null;
            }
        }

        return $result;
    }

    /**
     * Generate HTML for codes table
     *
     * @param array $codes
     * @return string
     */
    public static function tableHtml( array $codes )
    {
        $tbody = '';
        foreach ( $codes as $code => $description ) {
            $tbody .= sprintf(
                '<tr><td class="p-0"><input value="{%s}" class="border-0 bookly-outline-0" readonly="readonly" onclick="this.select()" /> &ndash; %s</td></tr>',
                $code,
                esc_html( $description )
            );
        }

        return '<table><tbody>' . $tbody . '</tbody></table>';
    }
}