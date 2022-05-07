<?php
/*
Plugin Name: XML File Splitter for WP All Export
Description: Split XML files into specific chunks.
Version: 1.0
Author: Trey
*/

namespace XML_File_Splitter_WPAE;

class File_Splitter {

	public $chunkon      = 'item';
	public $records_per  = 1;
	public $chunks       = 0;
	public $payload      = 'x';
	public $itemcount    = 0;
    public $root_element = '<root>';
    public $filename     = 'output';


	public function split_file( $file, $repeating_tag = 'item', $split_to_records = 1, $root_element = '<root>', $output_filename = 'output' ) {
		$this->chunkon      = $repeating_tag;
		$this->itemlimit    = $split_to_records;
		$this->chunks       = 0;
        $this->root_element = $root_element;
        $this->filename     = $output_filename;

		$xml = $this->create_xml_parser( 'UTF-8', false );

		$file_pointer = fopen( $file, 'r' );

		while( ! feof( $file_pointer ) ) {
			$chunk = fgets( $file_pointer, 10240 );
			xml_parse( $xml, $chunk, feof( $file_pointer ) );
		}
		xml_parser_free( $xml );
	
		$this->process_chunk( true, $this->filename );
	}

	public function create_xml_parser( $encoding = 'UTF-8', $bare_xml = false ) {
		$current_xml = xml_parser_create( $encoding );
		xml_parser_set_option(          $current_xml, XML_OPTION_CASE_FOLDING, false );
		xml_parser_set_option(          $current_xml, XML_OPTION_TARGET_ENCODING, $encoding );
		xml_set_element_handler(        $current_xml, array( $this, 'start_element' ), array( $this, 'end_element') );
		xml_set_character_data_handler( $current_xml, array( $this, 'data_handler' ) );
		xml_set_default_handler(        $current_xml, array( $this, 'default_handler' ) );

		if ( $bare_xml ) {
			xml_parse( $current_xml, '<?xml version="1.0"?>', 0 );
		}

		return $current_xml;
	}
	
	public function process_chunk( $last_chunk = false, $filename = 'output' ) {
		if ( '' == $this->payload ) {
			return;
		}
		
		$uploads = wp_upload_dir();
		$file    = $uploads['basedir'] . DIRECTORY_SEPARATOR . "{$filename}-" . $this->chunks . ".xml";
		
		$xml_pointer = fopen( $file, "w");
		fwrite( $xml_pointer, '<?xml version="1.0"?>'."\n");
		fwrite( $xml_pointer, $this->root_element );
		fwrite( $xml_pointer, $this->payload );
		$last_chunk || fwrite( $xml_pointer, str_replace( "<", "</", $this->root_element ) );
		fclose($xp);
		//print "Written {$file}\n";
		$this->chunks++;
		$this->payload    = '';
		$this->itemcount  = 0;
	}

	public function start_element($xml, $tag, $attrs = array()) {
		GLOBAL $PAYLOAD, $CHUNKS, $ITEMCOUNT, $CHUNKON;
		if ( ! ( $this->chunks || $this->itemcount ) ) {
			if ( $this->chunkon == strtolower( $tag ) ) {
				$this->payload = '';
			}
		}
		$this->payload .= "<{$tag}";

		foreach( $attrs as $k => $v ) {
			$this->payload .= " {$k}=\"" .addslashes( $v ).'"';
		}
		$this->payload .= '>';
	}


	public function end_element($xml, $tag) {
		$this->data_handler( null, "</{$tag}>" );

		if ( $this->chunkon == strtolower( $tag ) ) {
			if ( ++$this->itemcount >= $this->itemlimit ) {
				$this->process_chunk( false, $this->filename );
			}
		}
	}

	function data_handler( $xml, $data ) {
		$this->payload .= $data;
	}

	public function default_handler($xml, $data) {
		// Wild Text Fallback Handler / WTFHandler
	}
}