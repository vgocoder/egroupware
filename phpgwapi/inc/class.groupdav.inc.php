<?php
/**
 * eGroupWare: GroupDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007/8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once('HTTP/WebDAV/Server.php');

/**
 * eGroupWare: GroupDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 *
 * @link http://www.groupdav.org GroupDAV spec
 */
class groupdav extends HTTP_WebDAV_Server
{
	/**
	 * GroupDAV namespace
	 */
	const GROUPDAV = 'http://groupdav.org/';
	/**
	 * CalDAV namespace
	 */
	const CALDAV = 'urn:ietf:params:xml:ns:caldav';
	/**
	 * CardDAV namespace
	 */
	const CARDDAV = 'urn:ietf:params:xml:ns:carddav';
	/**
	 * Realm and powered by string
	 */
	const REALM = 'eGroupWare CalDAV/CardDAV/GroupDAV server';

	var $dav_powered_by = self::REALM;

	var $root = array(
		'calendar' => array(self::GROUPDAV => 'vevent-collection', self::CALDAV => 'calendar'),
		'addressbook' => array(self::GROUPDAV => 'vcard-collection', self::CARDDAV => 'addressbook'),
		'infolog' => array(self::GROUPDAV => 'vtodo-collection'),
	);
	/**
	 * Debug level: 0 = nothing, 1 = function calls, 2 = more info, 3 = complete $_SERVER array
	 *
	 * The debug messages are send to the apache error_log
	 *
	 * @var integer
	 */
	var $debug = 1;

	/**
	 * eGW's charset
	 *
	 * @var string
	 */
	var $egw_charset;
	/**
	 * Reference to the translation class
	 *
	 * @var translation
	 */
	var $translation;
	/**
	 * Instance of our application specific handler
	 *
	 * @var groupdav_handler
	 */
	var $handler;

	function __construct()
	{
		if ($this->debug > 2) foreach($_SERVER as $name => $val) error_log("groupdav: \$_SERVER[$name]='$val'");

		parent::HTTP_WebDAV_Server();

		$this->translation =& $GLOBALS['egw']->translation;
		$this->egw_charset = $this->translation->charset();
	}

	function _instancicate_handler($app)
	{
		$this->handler = groupdav_handler::app_handler($app);
	}

	/**
	 * OPTIONS request, allow to modify the standard responses from the pear-class
	 *
	 * @param string $path
	 * @param array &$dav
	 * @param array &$allow
	 */
	function OPTIONS($path, &$dav, &$allow)
	{
		list(,$app) = explode('/',$path);

		switch($app)
		{
			case 'calendar':
				$dav[] = 'calendar-access';
				break;
			case 'addressbook':
				$dav[] = 'addressbook';
				break;
		}
		// not yet implemented: $dav[] = 'access-control';
	}

	/**
	 * PROPFIND and REPORT method handler
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function PROPFIND(&$options, &$files,$method='PROPFIND')
	{
		if ($this->debug) error_log(__CLASS__."::$method(".str_replace(array("\n",'    ',"\t"),'',print_r($options,true)).')');

		// parse path in form [/account_lid]/app[/more]
		if (!self::_parse_path($options['path'],$id,$app,$user) && $app && !$user)
		{
			if ($this->debug > 1) error_log(__CLASS__."::$method: user=$user, app=$app, id=$id: 404 not found!");
			return '404 Not Found';
		}
		if ($this->debug > 1) error_log(__CLASS__."::$method: user=$user, app='$app', id=$id");

		$files = array();

		if (!$app)	// root folder containing apps
		{
			$files['files'][] = array(
            	'path'  => '/',
            	'props' => array(
            		self::mkprop('displayname','eGroupWare'),
            		self::mkprop('resourcetype','collection'),
	            	// adding the calendar extra property (calendar-home-set, etc.) here, allows apple iCal to "autodetect" the URL
					self::mkprop(groupdav::CALDAV,'calendar-home-set',$_SERVER['SCRIPT_NAME'].'/calendar/'),
				),
            );

			foreach($this->root as $app => $data)
			{
				if (!$GLOBALS['egw_info']['user']['apps'][$app]) continue;	// no rights for the given app

				$extra_props = 'groupdav_'.$app.'::extra_properties';

				$files['files'][] = array(
	            	'path'  => '/'.$app.'/',
	            	'props' => call_user_func('groupdav_'.$app.'::extra_properties',array(
	            		self::mkprop('displayname',$this->translation->convert(lang($app),$this->egw_charset,'utf-8')),
	            		self::mkprop('resourcetype',$this->_resourcetype($app)),
	            	)),
	            );
			}
			return true;
		}
		if (!$GLOBALS['egw_info']['user']['apps'][$app])
		{
			error_log(__CLASS__."::$method(path=$options[path]) 403 Forbidden: no app rights");
			return '403 Forbidden';	// no rights for the given app
		}
		if (($handler = groupdav_handler::app_handler($app,$this->debug)))
		{
			if ($method != 'REPORT' && !$id)	// no self URL for REPORT requests (only PROPFIND) or propfinds on an id
			{
				$files['files'][] = array(
		        	'path'  => '/'.$app.'/',
		        	'props' => $handler->extra_properties(array(
		            	self::mkprop('displayname',$this->translation->convert(lang($app),$this->egw_charset,'utf-8')),
		            	// Kontact doubles the folder, if the self URL contains the GroupDAV/CalDAV resourcetypes
		        		self::mkprop('resourcetype', $this->_resourcetype($app,strpos($_SERVER['HTTP_USER_AGENT'],'KHTML') !== false)),
		        	)),
		        );
			}
			return $handler->propfind($options['path'],$options,$files,$user,$id);
		}
		return '501 Not Implemented';
	}

	/**
	 * Return resourcetype(s) for a given app
	 *
	 * @param string $app
	 * @param boolean $no_extra_types=false should the GroupDAV and CalDAV types be added (Kontact has problems with it in self URL)
	 * @return array or DAV properties generated via
	 */
	function _resourcetype($app,$no_extra_types=false)
	{
		$resourcetype = array(
			self::mkprop('collection','collection'),
		);
		if (!$no_extra_types)
		{
			foreach($this->root[$app] as $ns => $type)
			{
				$resourcetype[] = self::mkprop($ns,'resourcetype', $type);
			}
		}
		return $resourcetype;
	}

	/**
	 * CalDAV/CardDAV REPORT method handler
	 *
	 * just calls PROPFIND()
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function REPORT(&$options, &$files)
	{
		if ($this->debug > 1) error_log(__METHOD__.'('.str_replace(array("\n",'    '),'',print_r($options,true)).')');

		return $this->PROPFIND($options,$files,'REPORT');
	}

	/**
	 * CalDAV/CardDAV REPORT method handler to get HTTP_WebDAV_Server to process REPORT requests
	 *
	 * Just calls http_PROPFIND()
	 */
	function http_REPORT()
	{
		parent::http_PROPFIND('REPORT');
	}

	/**
	 * GET method handler
	 *
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function GET(&$options)
	{
		if ($this->debug) error_log(__METHOD__.'('.print_r($options,true).')');

		if (!$this->_parse_path($options['path'],$id,$app,$user))
		{
			return '404 Not Found';
		}
		if (($handler = groupdav_handler::app_handler($app,$this->debug)))
		{
			return $handler->get($options,$id);
		}
		return '501 Not Implemented';
	}

	/**
	 * PUT method handler
	 *
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function PUT(&$options)
	{
		// read the content in a string, if a stream is given
		if (isset($options['stream']))
		{
			$options['content'] = '';
			while(!feof($options['stream']))
			{
				$options['content'] .= fread($options['stream'],8192);
			}
		}
		if ($this->debug) error_log(__METHOD__.'('.print_r($options,true).')');

		if (!$this->_parse_path($options['path'],$id,$app,$user))
		{
			return '404 Not Found';
		}
		if (($handler = groupdav_handler::app_handler($app,$this->debug)))
		{
			$status = $handler->put($options,$id,$user);
			// set default stati: true --> 204 No Content, false --> should be already handled
			if (is_bool($status)) $status = $status ? '204 No Content' : '400 Something went wrong';
			return $status;
		}
		return '501 Not Implemented';
	}

	/**
	 * DELETE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function DELETE($options)
	{
		if ($this->debug) error_log(__METHOD__.'('.print_r($options,true).')');

		if (!$this->_parse_path($options['path'],$id,$app,$user))
		{
			return '404 Not Found';
		}
		if (($handler = groupdav_handler::app_handler($app,$this->debug)))
		{
			$status = $handler->delete($options,$id);
			// set default stati: true --> 204 No Content, false --> should be already handled
			if (is_bool($status)) $status = $status ? '204 No Content' : '400 Something went wrong';
			return $status;
		}
		return '501 Not Implemented';
	}

	/**
	 * MKCOL method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MKCOL($options)
	{
		if ($this->debug) error_log(__METHOD__.'('.print_r($options,true).')');

		return '501 Not Implemented';
	}

	/**
	 * MOVE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MOVE($options)
	{
		if ($this->debug) error_log(__METHOD__.'('.print_r($options,true).')');

		return '501 Not Implemented';
	}

	/**
	 * COPY method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function COPY($options, $del=false)
	{
		if ($this->debug) error_log('groupdav::'.($del ? 'MOVE' : 'COPY').'('.print_r($options,true).')');

		return '501 Not Implemented';
	}

	/**
	 * LOCK method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function LOCK(&$options)
	{
		error_log(__METHOD__.'('.str_replace(array("\n",'    '),'',print_r($options,true)).')');

		self::_parse_path($options['path'],$id,$app,$user);
		$path = egw_vfs::app_entry_lock_path($app,$id);

		// get the app handler, to check if the user has edit access to the entry (required to make locks)
		$handler = groupdav_handler::app_handler($app);

		// TODO recursive locks on directories not supported yet
		if (!$id || !empty($options['depth']) || !$handler->check_access(EGW_ACL_EDIT,$id))
		{
			return '409 Conflict';
		}
		$options['timeout'] = time()+300; // 5min. hardcoded

		// dont know why, but HTTP_WebDAV_Server passes the owner in D:href tags, which get's passed unchanged to checkLock/PROPFIND
		// that's wrong according to the standard and cadaver does not show it on discover --> strip_tags removes eventual tags
		if (($ret = egw_vfs::lock($path,$options['locktoken'],$options['timeout'],strip_tags($options['owner']),
			$options['scope'],$options['type'],isset($options['update']),false)) && !isset($options['update']))		// false = no ACL check
		{
			return $ret ? '200 OK' : '409 Conflict';
		}
		return $ret;
	}

	/**
	 * UNLOCK method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function UNLOCK(&$options)
	{
		self::_parse_path($options['path'],$id,$app,$user);
		$path = egw_vfs::app_entry_lock_path($app,$id);

		error_log(__METHOD__.'('.str_replace(array("\n",'    '),'',print_r($options,true)).") path=$path");
		return egw_vfs::unlock($path,$options['token']) ? '204 No Content' : '409 Conflict';
	}

	/**
	 * checkLock() helper
	 *
	 * @param  string resource path to check for locks
	 * @return bool   true on success
	 */
	function checkLock($path)
	{
		self::_parse_path($path,$id,$app,$user);
		$path = egw_vfs::app_entry_lock_path($app,$id);

		return egw_vfs::checkLock($path);
	}

	/**
	 * Parse a path into it's id, app and user parts
	 *
	 * @param string $path
	 * @param int &$id
	 * @param string &$app addressbook, calendar, infolog (=infolog)
	 * @param int &$user
	 * @return boolean true on success, false on error
	 */
	function _parse_path($path,&$id,&$app,&$user)
	{
		$parts = explode('/',$path);

		list($id) = explode('.',array_pop($parts));		// remove evtl. .ics extension

		$app = array_pop($parts);

		if (($user = array_pop($parts)))
		{
			$user = $GLOBALS['egw']->accounts->name2id($user,'account_lid',$app != 'addressbook' ? 'u' : null);
		}
		else
		{
			$user = $GLOBALS['egw_info']['user']['account_id'];
		}
		if (!($ok = $id && in_array($app,array('addressbook','calendar','infolog')) && $user))
		{
			error_log(__METHOD__."('$path') returning false: id=$id, app='$app', user=$user");
		}
		return $ok;
	}
}
