<?php

if ( ! function_exists ( 'mime_content_type' ) )
{
   function mime_content_type ( $f )
   {
       return trim ( exec ('file -bi ' . escapeshellarg ( $f ) ) ) ;
   }
}

require_once 'Item.php';

/**
 * @package Omeka
 * 
 **/
class File extends Kea_Record { 
    

	public function setUp() {
		$this->hasOne("Item", "File.item_id");
	}

	public function setTableDefinition() {
		$this->setTableName('files');
		
        $this->hasColumn("title","string",400);
		$this->hasColumn("publisher","string",400);
		$this->hasColumn("language","string",40);
		$this->hasColumn("relation","string",null);
		$this->hasColumn("coverage","string",null);
		$this->hasColumn("rights","string",null);
		$this->hasColumn("description","string", null);
		$this->hasColumn("source","string",null);
		$this->hasColumn("subject","string",400);
		$this->hasColumn("creator","string",400);
		$this->hasColumn("additional_creator","string",400);
		$this->hasColumn("date","date");
		$this->hasColumn("added","timestamp");
		$this->hasColumn("modified","timestamp");
		$this->hasColumn("format", "string");
		$this->hasColumn("item_id","integer");	 
		$this->hasColumn("transcriber","string",null);
		$this->hasColumn("producer","string",null);
		$this->hasColumn("render_device","string",null);
		$this->hasColumn("render_details","string",null);
		$this->hasColumn("capture_date", "timestamp");
		$this->hasColumn("capture_device","string",null);
		$this->hasColumn("capture_details", "string",null);
		$this->hasColumn("change_history","string",null);
		$this->hasColumn("watermark","string",null);
		$this->hasColumn("authentication","string",null);
		$this->hasColumn("encryption", "string",null);
		$this->hasColumn("compression", "string",null);
		$this->hasColumn("post_processing","string",null);
		$this->hasColumn("archive_filename","string",400);
		$this->hasColumn("fullsize_filename","string",400);
		$this->hasColumn("original_filename","string",400);
		$this->hasColumn("thumbnail_filename","string",400);
		$this->hasColumn("size","integer");
		$this->hasColumn("mime_browser","string",400);
		$this->hasColumn("mime_php","string",400);
		$this->hasColumn("mime_os","string",400);
		$this->hasColumn("type_os","string",400);
		
		$this->index('item', array('fields' => array('item_id')));
    }
	
	/**
	 * Retrieve the path for the image
	 *
	 * @return string
	 **/
	public function getPath($type='fullsize')
	{
		switch ($type) {
			case 'fullsize':
				return FULLSIZE_DIR.DIRECTORY_SEPARATOR.$this->fullsize_filename;
				break;
			case 'thumbnail':
				return THUMBNAIL_DIR.DIRECTORY_SEPARATOR.$this->thumbnail_filename;
			case 'archive':
			default:
				return FILES_DIR.DIRECTORY_SEPARATOR.$this->archive_filename;
				break;
		}
	}
	
	/**
	 * Stole this jazz from the old File model
	 *
	 * @return void
	 * 
	 **/
	public function upload($form_name, $index, $useExif = false) {
		$error = $_FILES[$form_name]['error'][$index];

		if( $error == UPLOAD_ERR_OK ) {
				$tmp = $_FILES[$form_name]['tmp_name'][$index];
				$name = $_FILES[$form_name]['name'][$index];
				$new_name = explode( '.', $name );
				$new_name[0] .= '_' . substr( md5( mt_rand() + microtime( true ) ), 0, 10 );
				$new_name_string = implode( '.', $new_name );
				$path = FILES_DIR.DIRECTORY_SEPARATOR.$new_name_string;
				
				if( !is_writable(dirname($path)) )
				{
					throw new Exception ('Unable to write to '. dirname($path) . ' directory; improper permissions');
				}
				
				if( !move_uploaded_file( $tmp, $path ) ) throw new Exception('Could not save file.');
				
				//set the attributes of this file
				$this->size = $_FILES[$form_name]['size'][$index];
				$this->authentication = md5_file( $path );
				
				$this->mime_browser = $_FILES[$file_form_name]['type'][$index];
				$this->mime_php = mime_content_type( $path );
				$this->mime_os = trim( exec( 'file -ib ' . trim( escapeshellarg ( $path ) ) ) );
				$this->type_os = trim( exec( 'file -b ' . trim( escapeshellarg ( $path ) ) ) );

				$this->original_filename = $name;
				$this->archive_filename = $new_name_string;
				
				//Retrieve the image sizes from the database
				$full_constraint = get_option('fullsize_constraint');
				$thumb_constraint = get_option('thumbnail_constraint');
				
				$this->fullsize_filename = $this->createImage(FULLSIZE_DIR, $path, $full_constraint );
				
				$this->thumbnail_filename = $this->createImage(THUMBNAIL_DIR, $path, $thumb_constraint );
				
		} else {
			// Ignore error '4' - no file uploaded and error '0' - file uploaded correctly
				switch( $error ) {

					// 1 - File exceeds upload size in php.ini
					// 2 - File exceeds upload size set in MAX_FILE_SIZE
					case( '1' ):
					case( '2' ):
						throw new Exception(
							$_FILES[$file_form_name]['name'][$key] . ' exceeds the maximum file size.' . $_FILES[$file_form_name]['size'][$key]
						);
					break;
					
					// 3 - File partially uploaded
					case( '3' ):
						throw new Exception(
							$_FILES[$file_form_name]['name'][$key] . ' was only partially uploaded.  Please try again.'
						);
					break;
					
					// 6 - Missing Temp folder
					// 7 - Can't write file to disk
					case( '6' ):
					case( '7' ):
						throw new Exception(
							'There was a problem saving the files to the server.  Please contact an administrator for further assistance.'
						);
					break;
				}
		}
	}
	
	/**
	 * Also ripped off/modded from old File model
	 *
	 * @return void
	 * 
	 **/
	protected function createImage( $new_dir, $old_path, $constraint, $no_enlarge = true ) {
		$convertPath = get_option('path_to_convert');
		
		if(!$this->checkForImageMagick($convertPath)) {
			throw new Exception( 'ImageMagick library is required for thumbnail generation' );
		}
		
		if( !is_dir($new_dir) )
		{
			throw new Exception ('Invalid directory to put new image');
		}
		if( !is_writable($new_dir) )
		{
			throw new Exception ('Unable to write to '. $new_dir . ' directory; improper permissions');
		}
		
		if( file_exists( $old_path ) && is_readable( $old_path ) && getimagesize( $old_path ) )
		{	
			list( $width, $height, $type ) = getimagesize( $old_path );
			
			$filename = basename( $old_path );
			$new_name = explode( '.', $filename );
			$new_name[0] .= '_' . basename($new_dir);
			//ensures that all generated files are jpeg
			$new_name[1] = 'jpg';
			$imagename = implode( '.', $new_name );
			$new_path = rtrim( $new_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $imagename;
			
			$old_path = escapeshellarg( $old_path );
			$new_path = escapeshellarg( $new_path );
			
			if(!$constraint) {
				throw new Exception( 
					'Image creation failed - Image size constraint must be specified within application settings' 
				);
			}
			
			//Landscape aspect-ratio
			if($width > $height) {
				//Width is the constraint
				
				$new_width = $constraint;
				$new_height = ($constraint / $width) * $height;
			}
			//Portrait aspect-ratio
			else {
				//Height is the constraint

				$new_height = $constraint;
				$new_width = ($constraint / $height) * $width;
			}
			
			$command = $convertPath . ' ' . $old_path . ' -resize \'' . $new_width . 'x' . $new_height . '>\' ' . $new_path;
			
			//We probably don't want to make images that are any bigger than the raw file
			if( $no_enlarge )
			{
				if ( ( $new_width > $width ) || ( $new_height > $height ) )
				{
					$command = $convertPath . ' ' . $old_path . ' ' . $new_path;
				}
			}

			exec( $command, $result_array, $result_value );
			if( $result_value == 0 )
			{
				return $imagename;	
			}
			else
			{
			
				throw new Exception(
					'Something went wrong with image creation.  Ensure that the thumbnail directories have appropriate write permissions.'
				);
			}
		}
	}
	
	private function checkForImageMagick($path) {
		exec( $path . ' -version', $convert_version, $convert_return );
		return ( $convert_return == 0 );
	}
	
	protected function deleteFiles() {
		$files = array( 
			(FULLSIZE_DIR . DIRECTORY_SEPARATOR . $this->fullsize_filename), 
			(THUMBNAIL_DIR . DIRECTORY_SEPARATOR . $this->thumbnail_filename), 
			(FILES_DIR . DIRECTORY_SEPARATOR . $this->archive_filename) );
		
		foreach( $files as $file )
		{
			if( file_exists($file) && !is_dir($file) ) unlink($file);
		}
	}
	public function delete() {
		$this->deleteFiles();
		parent::delete();
	}
}  	 

?>