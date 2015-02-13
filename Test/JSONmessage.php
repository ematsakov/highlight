<?php
namespace icelineLtd\RESTFOSDemoBundle\Service;

use icelineLtd\RESTFOSDemoBundle\Validator\Validator;
use icelineLtd\RESTFOSDemoBundle\Entity\UserCollectionInterface;
use icelineLtd\RESTFOSDemoBundle\Service\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Bridge\Monolog\Logger as Logger;
use Symfony\Component\Validator\Validator\LegacyValidator as CoreValidator;

/**
 * JSONmessage 
 * This class doesn't DDD very well.  I guess I could name it APIMessage 
 *
 * @package 
 * @version $id$
 * @author oab1 / Owen Beresford / owen@iceline.ltd.uk  
 * @license AGPL {@link http://www.gnu.org/licenses/agpl-3.0.html}
 */
class JSONmessage 
{
	const POST_VAR_NAME='data';

	protected $log;
	protected $error;
	protected $impl;

	/**
	 * __construct
	 * 
	 * @param Logger $l 
	 * @return <self>
	 */
	function __construct(Logger $l) {{{
		$this->log	= $l;
		$this->impl	= null;
		$this->error= null;
	}}}

	/**
	 * setValidator
	 * 
	 * @param Validator $v 
	 * @return <self>
	 */
	function setValidator(Validator $v) {
		$this->impl=$v;
		return $this;
	}

	/**
	 * readMultiplex ~ PHPs handling of PUT is awkward 
	 * As i started using FOS REST, I dont need this.
	 * 
	 * @param Request $reqt 
	 * @return string
	 */
	function readMultiplex(Request $reqt=null) {
		if(is_null($reqt)) {
			$reqt	= Request::createFromGlobals();
		}
# if a GET or a POST...
		$data		= trim($reqt->get( JSONmessage::POST_VAR_NAME, '{}'));
# should cover POST and GET
		if(strlen($data)>3) {
			return $data;
		}

# according to some manauls, this will access PUT
		$data		= $reqt->getContent();
$this->log->info("XXX ".$data);
		if(strlen($data)>3) {
			return $data;
		}

# in code outside of SF2, this reliably accesses PUT requests
		$data		='';
		$fh			=fopen('php://input', 'r');
$this->log->info("XXX ".debug_zval_dump($fh));
		while(!feof($fh)) {
$this->log->info("XXX '$data' ".debug_zval_dump($fh));

			$data   .=fread($fh, 1024);
		}
		fclose($fh);
		if(strlen($data)>3) {
			return $data;
		}

		$this->log->info("Can't find anything to read '$data'.");
		return "{}";
	}

	/**
	 * extract ~ I now know this would be easier as the FOS REST bundle, but didn't know about this in the tight timescales at the start.
	 * 
	 * @param array $extra ~ overrides if necessary 
	 * @param Request $reqt ~ OPTIONAL ~ if you have a Request, don't build a new one
	 * @assert gettype($this->obj->extract(array())) == 'Array' 
	 * @return the generated data
	 */
	function extract(array $extra, ParameterBag $reqt) {{{
		$this->error='';
#		$data		= $this->readMultiplex($reqt);
#		$data		= str_replace('\\', '\\\\', $data);
#$this->log->info("Read data:".$data);
#		$data		= json_decode($data, true, 512, JSON_BIGINT_AS_STRING);
#		if(!is_array($data)) {
#			throw new BadRequestException("Input validation failed ~ not JSON");	
#		}
		foreach($extra as $k=>$v) {
			$reqt->set($k, $v);
		}

		if(!is_object($this->impl)) {
			throw new BadRequestException("Must register the Validator before running extract()", 500);
		}
		$data		= $reqt->all();
		if($this->impl->validate($data)) {
			return $data;
		}
		$this->error=$this->impl->getError();
		if(is_array($this->error)) {
			throw new BadRequestException(array_pop($this->error), $this->impl->getErrorCode());
		} elseif(is_string($this->error)) {
			throw new BadRequestException($this->error, $this->impl->getErrorCode());
		} else {
			throw new BadRequestException("Input validation failed.", 400);
		}
	}}}
	
	/**
	 * response ~ util function to render JSON to the client
	 * 
	 * @param string $json 
	 * @param int status
	 * @return a Response object
	 * @assert get_class($this->obj->response('{"error":0,"errstr":"operation worked."}')) == 'Symfony\Component\HttpFoundation\JsonResponse'
	 * @assert get_class($this->obj->response(array("error"=>0,"errstr"=>"operation worked."))) == 'Symfony\Component\HttpFoundation\JsonResponse'
	 */
	function response($json, $status=200) {{{
#		if(is_array($json)) {
#			$json	= json_encode($json);
#		}
		$response	= new JsonResponse($json, $status );
#		$response->setContent($json);
#		$response->setStatusCode($status);
		$this->log->debug("Created a ".strlen($response->getContent())." JSON response for code $status"); 
		$response->sendHeaders();
		return $response;
	}}}

}

