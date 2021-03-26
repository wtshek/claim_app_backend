<?php

require_once( dirname(__FILE__) . '/mime_type_lib.php' );

/**
 * Copies file.
 * @param   source      Path to the source file
 * @param   dest        The destination path
 * @return  TRUE on success or FALSE on failure
 */
function s3_copy( $source, $dest )
{
    global $kernel;
    if ( s3_file_exists($source) )
    {
        try
        {
            if ( s3_file_exists($dest) )
            {
                s3_unlink( $dest );
            }
            $kernel->s3->copyObject(array(
                'Bucket' => $kernel->conf['s3_bucket'],
                'Key' => $dest,
                'CopySource' => urlencode( "{$kernel->conf['s3_bucket']}/$source" ),
            ));
            return TRUE;
        }
        catch ( Exception $e )
        {
            return FALSE;
        }
    }
    return FALSE;
}

/**
 * Deletes a folder.
 * @param   filename    Path to the folder
 * @return  TRUE on success or FALSE on failure
 */
function s3_deleteDir( $filename )
{
    global $kernel;
    $filename = rtrim( $filename, '/' ) . '/';
    if ( s3_file_exists($filename) )
    {
        try
        {
            // List objects
            $paginator = $kernel->s3->getPaginator( 'ListObjects', array(
                'Bucket' => $kernel->conf['s3_bucket'],
                'Prefix' => $filename
            ) );
            $objects = array();
            foreach ( $paginator->search('[CommonPrefixes[].Prefix, Contents[].Key][]') as $key )
            {
                $objects[] = array( 'Key' => $key );
                unlink( s3_get_cache_name($key, 'head') );
                unlink( s3_get_cache_name($key, 'imagesize') );
            }

            // Delete objects
            $object_chunks = array_chunk( $objects, 1000 );
            foreach ( $object_chunks as $objects )
            {
                $kernel->s3->deleteObjects(array(
                    'Bucket' => $kernel->conf['s3_bucket'],
                    'Delete' => array(
                        'Objects' => $objects,
                        'Quiet' => TRUE
                    )
                ));
            }
        }
        catch ( Exception $e )
        {
            return FALSE;
        }
    }
    return FALSE;
}

/**
 * Checks whether a file or directory exists.
 * @param   filename    Path to the file or directory
 * @return  TRUE if the file or directory specified by filename exists; FALSE otherwise
 */
function s3_file_exists( $filename )
{
    global $kernel;

    // Bucket
    if ( $filename === '' )
    {
        try
        {
            $result = $kernel->s3->headBucket( array('Bucket' => $kernel->conf['s3_bucket']) );
            return TRUE;
        }
        catch ( Exception $e )
        {
            return FALSE;
        }
    }

    // Object
    else
    {
        // Use listObjectsV2 instead of doesObjectExist as the latter does not work reliably
        // https://stackoverflow.com/questions/55596903/proper-way-to-check-if-a-folder-exists-in-aws-s3-from-aws-emr
        // return $kernel->s3->doesObjectExist( $kernel->conf['s3_bucket'], $filename );
        $result = $kernel->s3->listObjectsV2( array(
            'Bucket' => $kernel->conf['s3_bucket'],
            'MaxKeys' => 1,
            'Prefix' => $filename
        ) );
        return $result['KeyCount'] > 0;
    }
}

/**
 * Reads entire file into a string.
 * @param   filename    Name of the file to read
 * @return  The read data or FALSE on failure
 */
function s3_file_get_contents( $filename )
{
    global $kernel;
    try
    {
        $result = $kernel->s3->getObject( array(
            'Bucket' => $kernel->conf['s3_bucket'],
            'Key' => $filename
        ) );
        return $result['Body'];
    }
    catch ( Exception $e )
    {
        return FALSE;
    }
}

/**
 * Write a string to a file.
 * @param   filename    Path to the file where to write the data
 * @param   data        The data to write
 * @return  The number of bytes that were written to the file, or FALSE on failure
 */
function s3_file_put_contents( $filename, $data )
{
    global $kernel;
    try
    {
        $result = $kernel->s3->putObject( array(
            'Bucket' => $kernel->conf['s3_bucket'],
            'Key' => $filename,
            'ContentType' => get_file_mime_type( $filename ),
            'Body' => $data
        ) );
        unlink( s3_get_cache_name($filename, 'head') );
        unlink( s3_get_cache_name($filename, 'imagesize') );
        return strlen( $data );
    }
    catch ( Exception $e )
    {
        return FALSE;
    }
}

/**
 * Gets file modification time.
 * @param   filename    Path to the file (or metadata)
 * @return  The time the file was last modified, or FALSE on failure
 */
function s3_filemtime( $filename )
{
    $metadata = is_string( $filename ) ? s3_head_object( $filename ) : $filename;
    return $metadata === FALSE ? FALSE : $metadata['last_modified'];
}

/**
 * Gets file size.
 * @param   filename    Path to the file (or metadata)
 * @return  The size of the file in bytes, or FALSE in case of an error
 */
function s3_filesize( $filename )
{
    $metadata = is_string( $filename ) ? s3_head_object( $filename ) : $filename;
    return $metadata === FALSE ? FALSE : $metadata['content_length'];
}

/**
 * Returns path used for cache file.
 * @param   filename    Path to the file
 * @param   type        The type
 * @return  The path of the cache file
 */
function s3_get_cache_name( $filename, $type )
{
    return dirname( dirname(__FILE__) ) . '/meta/' . md5( $filename ) . '.' . $type;
}

/**
 * Get the size of an image.
 * @param   filename    Specifies the file you wish to retrieve information about
 * @return  An array with up to 7 elements, or FALSE on failure
 */
function s3_getimagesize( $filename )
{
    global $kernel;
    $cache_path = s3_get_cache_name( $filename, 'imagesize' );
    if ( file_exists($cache_path) )
    {
        $imagesize = json_decode( file_get_contents($cache_path), TRUE );
        if ( !is_null($imagesize) )
        {
            return $imagesize;
        }
    }
    $contents = s3_file_get_contents( $filename );
    if ( $contents !== FALSE )
    {
        $imagesize = getimagesizefromstring( $contents );
        file_put_contents( $cache_path, json_encode($imagesize) );
        return $imagesize;
    }
    return FALSE;
}

/**
 * Retrieves metadata from an object without returning the object itself.
 * @param   filename    Path to the file
 * @return  An array of metadata on success, or FALSE on failure
 */
function s3_head_object( $filename )
{
    global $kernel;
    $cache_path = s3_get_cache_name( $filename, 'head' );
    if ( file_exists($cache_path) )
    {
        $metadata = json_decode( file_get_contents($cache_path), TRUE );
        if ( !is_null($metadata) && $metadata['expires'] < time() )
        {
            return $metadata;
        }
    }
    try
    {
        $result = $kernel->s3->headObject( array(
            'Bucket' => $kernel->conf['s3_bucket'],
            'Key' => $filename
        ) );
        $metadata = array(
            'last_modified' => strtotime( $result['LastModified'] ),
            'content_length' => intval( $result['ContentLength'] ),
            'content_type' => $result['ContentType'],
            'expires' => strtotime( $result['Expires'] ),
            'effective_uri' => $result['@metadata']['effectiveUri']
        );
        file_put_contents( $cache_path, json_encode($metadata) );
        return $metadata;
    }
    catch ( Exception $e )
    {
        return FALSE;
    }
}

/**
 * Tells whether the filename is a directory.
 * @param   filename    Path to the file (or metadata)
 * @return  TRUE if the filename exists and is a directory, FALSE otherwise
 */
function s3_is_dir( $filename )
{
    $metadata = is_string( $filename ) ? s3_head_object( $filename ) : $filename;
    return $metadata === FALSE ? FALSE : substr( $metadata['effective_uri'], -1, 1 ) == '/';
}

/**
 * Tells whether the filename is a regular file.
 * @param   filename    Path to the file (or metadata)
 * @return  TRUE if the filename exists and is a regular file, FALSE otherwise
 */
function s3_is_file( $filename )
{
    $metadata = is_string( $filename ) ? s3_head_object( $filename ) : $filename;
    return $metadata === FALSE ? FALSE : substr( $metadata['effective_uri'], -1, 1 ) != '/';
}

/**
 * Makes directory.
 * @param   pathname    The directory path
 * @return  TRUE on success or FALSE on failure
 */
function s3_mkdir( $pathname )
{
    global $kernel;
    try
    {
        $result = $kernel->s3->putObject( array(
            'Bucket' => $kernel->conf['s3_bucket'],
            'Key' => $pathname,
            'ContentType' => 'binary/octet-stream'
        ) );
        return TRUE;
    }
    catch ( Exception $e )
    {
        return FALSE;
    }
}

/**
 * Renames a file or directory.
 * @param   oldname     The old name
 * @param   newname     The new name
 * @return  TRUE on success or FALSE on failure
 */
function s3_rename( $oldname, $newname )
{
    global $kernel;
    if ( s3_file_exists($oldname) )
    {
        try
        {
            // Delete object
            if ( s3_file_exists($newname) )
            {
                if ( s3_is_dir($newname) )
                {
                    s3_deleteDir( $newname );
                }
                else
                {
                    s3_unlink( $newname );
                }
            }

            // Rename directory
            if ( s3_is_dir($oldname) )
            {
                // Copy objects
                $paginator = $kernel->s3->getPaginator( 'ListObjects', array(
                    'Bucket' => $kernel->conf['s3_bucket'],
                    'Prefix' => $oldname
                ) );
                $old_objects = array();
                foreach ( $paginator->search('[CommonPrefixes[].Prefix, Contents[].Key][]') as $oldkey )
                {
                    $newkey = $newname . mb_substr( $oldkey, mb_strlen($oldname) );
                    $old_objects[] = array( 'Key' => $oldkey );
                    $kernel->s3->copyObject(array(
                        'Bucket' => $kernel->conf['s3_bucket'],
                        'Key' => $newkey,
                        'CopySource' => urlencode( "{$kernel->conf['s3_bucket']}/$oldkey" ),
                    ));
                    unlink( s3_get_cache_name($oldkey, 'head') );
                    unlink( s3_get_cache_name($oldkey, 'imagesize') );
                }

                // Delete old objects
                $old_object_chunks = array_chunk( $old_objects, 1000 );
                foreach ( $old_object_chunks as $old_objects )
                {
                    $kernel->s3->deleteObjects(array(
                        'Bucket' => $kernel->conf['s3_bucket'],
                        'Delete' => array(
                            'Objects' => $old_objects,
                            'Quiet' => TRUE
                        )
                    ));
                }
            }

            // Rename file
            else
            {
                $kernel->s3->copyObject(array(
                    'Bucket' => $kernel->conf['s3_bucket'],
                    'Key' => $newname,
                    'CopySource' => urlencode( "{$kernel->conf['s3_bucket']}/$oldname" ),
                ));
                $kernel->s3->deleteObject(array(
                    'Bucket' => $kernel->conf['s3_bucket'],
                    'Key' => $oldname
                ));
                unlink( s3_get_cache_name($oldname, 'head') );
                unlink( s3_get_cache_name($oldname, 'imagesize') );
            }

            return TRUE;
        }
        catch ( Exception $e )
        {
            return FALSE;
        }
    }
    return FALSE;
}

/**
 * List files and directories inside the specified path.
 * @param   directory       The directory that will be scanned
 * @return  An array of filenames on success, or FALSE on failure
 */
function s3_scandir( $directory )
{
    global $kernel;
    $paginator = $kernel->s3->getPaginator( 'ListObjects', array(
        'Bucket' => $kernel->conf['s3_bucket'],
        'Prefix' => $directory,
        'Delimiter' => '/'
    ) );
    $filenames = array( '.', '..' );
    foreach ( $paginator->search('[CommonPrefixes[].Prefix, Contents[].Key][]') as $key )
    {
        if ( $key !== $directory )
        {
            $filenames[] = rtrim( substr($key, strlen($directory)), '/' );
        }
    }
    return $filenames;
}

/**
 * Deletes a file.
 * @param   filename    Path to the file
 * @return  TRUE on success or FALSE on failure
 */
function s3_unlink( $filename )
{
    global $kernel;
    if ( s3_file_exists($filename) )
    {
        try
        {
            $kernel->s3->deleteObject(array(
                'Bucket' => $kernel->conf['s3_bucket'],
                'Key' => $filename
            ));
            unlink( s3_get_cache_name($filename, 'head') );
            unlink( s3_get_cache_name($filename, 'imagesize') );
            return TRUE;
        }
        catch ( Exception $e )
        {
            return FALSE;
        }
    }
    return FALSE;
}

?>