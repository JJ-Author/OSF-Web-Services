<?php
  
  namespace StructuredDynamics\osf\ws\dataset\delete\interfaces; 
  
  use \StructuredDynamics\osf\framework\Namespaces;  
  use \StructuredDynamics\osf\framework\Resultset;
  use \StructuredDynamics\osf\ws\framework\SourceInterface;
  use \StructuredDynamics\osf\ws\auth\registrar\access\AuthRegistrarAccess;
  use \StructuredDynamics\osf\ws\auth\lister\AuthLister;
  use \StructuredDynamics\osf\ws\framework\Solr;
  
  
  class DefaultSourceInterface extends SourceInterface
  {
    function __construct($webservice)
    {   
      parent::__construct($webservice);
      
      $this->compatibleWith = "3.0";
    }
    
    public function processInterface()
    {
      // Make sure there was no conneg error prior to this process call
      if($this->ws->conneg->getStatus() == 200)
      {
        // Make sure this is not an ontology dataset
        $query = "select ?holdOntology
                  from <" . $this->ws->wsf_graph . "datasets/>
                  where
                  {
                    <".$this->ws->datasetUri."> <http://purl.org/ontology/wsf#holdOntology> ?holdOntology .
                  }";

        $this->ws->sparql->query($query);

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_306->level);

          return;
        }
        else
        {
          while($this->ws->sparql->fetch_binding())
          {
            $holdOntology = $this->ws->sparql->value('holdOntology');

            if(strtolower($holdOntology) == "true")
            {
              $this->ws->conneg->setStatus(400);
              $this->ws->conneg->setStatusMsg("Bad Request");
              $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_306->name);
              $this->ws->conneg->setError($this->ws->errorMessenger->_306->id, $this->ws->errorMessenger->ws,
                $this->ws->errorMessenger->_306->name, $this->ws->errorMessenger->_306->description, 
                $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_306->level);

              return;            
            }
          }
        }

        // Remove the Graph description in the ".../datasets/"

        $this->ws->sparql->query("delete from <" . $this->ws->wsf_graph . "datasets/> 
                { 
                  <".$this->ws->datasetUri."> ?p ?o.
                }
                where
                {
                  graph <" . $this->ws->wsf_graph . "datasets/>
                  {
                    <".$this->ws->datasetUri."> ?p ?o.
                  }
                }");

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_301->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_301->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_301->name, $this->ws->errorMessenger->_301->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_301->level);
          return;
        }

        // Removing all accesses for this graph
        $ws_ara = new AuthRegistrarAccess("", "", $this->ws->datasetUri, "delete_all", "", "");

        $ws_ara->pipeline_conneg($this->ws->conneg->getAccept(), $this->ws->conneg->getAcceptCharset(),
          $this->ws->conneg->getAcceptEncoding(), $this->ws->conneg->getAcceptLanguage());

        $ws_ara->process();

        if($ws_ara->pipeline_getResponseHeaderStatus() != 200)
        {
          $this->ws->conneg->setStatus($ws_ara->pipeline_getResponseHeaderStatus());
          $this->ws->conneg->setStatusMsg($ws_ara->pipeline_getResponseHeaderStatusMsg());
          $this->ws->conneg->setStatusMsgExt($ws_ara->pipeline_getResponseHeaderStatusMsgExt());
          $this->ws->conneg->setError($ws_ara->pipeline_getError()->id, $ws_ara->pipeline_getError()->webservice,
            $ws_ara->pipeline_getError()->name, $ws_ara->pipeline_getError()->description,
            $ws_ara->pipeline_getError()->debugInfo, $ws_ara->pipeline_getError()->level);
          return;
        }

        // Drop the entire graph
        $this->ws->sparql->query("clear graph <" . $this->ws->datasetUri . ">");

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_302->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_302->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_302->name, $this->ws->errorMessenger->_302->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_302->level);

          return;
        }
        
        // Drop the revisions graph
        $revisionsDataset = rtrim($this->ws->datasetUri, '/').'/revisions/';
        
        $this->ws->sparql->query("clear graph <" . $revisionsDataset . ">");

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_310->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_310->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_310->name, $this->ws->errorMessenger->_310->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_310->level);

          return;
        }        

        // Drop the reification graph related to this dataset
        $this->ws->sparql->query("clear graph <" . $this->ws->datasetUri . "reification/>");

        if($this->ws->sparql->error())
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt("Error #dataset-delete-105");
          $this->ws->conneg->setError($this->ws->errorMessenger->_303->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_303->name, $this->ws->errorMessenger->_303->description, 
            $this->ws->sparql->errormsg(), $this->ws->errorMessenger->_303->level);

          return;
        }


        // Remove all documents from the solr index for this Dataset
        $solr = new Solr($this->ws->wsf_solr_core, $this->ws->solr_host, $this->ws->solr_port, $this->ws->fields_index_folder);

        if(!$solr->flushDataset($this->ws->datasetUri))
        {
          $this->ws->conneg->setStatus(500);
          $this->ws->conneg->setStatusMsg("Internal Error");
          $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_304->name);
          $this->ws->conneg->setError($this->ws->errorMessenger->_304->id, $this->ws->errorMessenger->ws,
            $this->ws->errorMessenger->_304->name, $this->ws->errorMessenger->_304->description, 
            $solr->errorMessage . '[Debugging information: ]'.$solr->errorMessageDebug,           
            $this->ws->errorMessenger->_304->level);

          return;
        }

        if($this->ws->solr_auto_commit === FALSE)
        {
          if(!$solr->commit())
          {
            $this->ws->conneg->setStatus(500);
            $this->ws->conneg->setStatusMsg("Internal Error");
            $this->ws->conneg->setStatusMsgExt($this->ws->errorMessenger->_305->name);
            $this->ws->conneg->setError($this->ws->errorMessenger->_305->id, $this->ws->errorMessenger->ws,
              $this->ws->errorMessenger->_305->name, $this->ws->errorMessenger->_305->description, 
              $solr->errorMessage . '[Debugging information: ]'.$solr->errorMessageDebug,
              $this->ws->errorMessenger->_305->level);

            return;
          }
        }

        // Invalidate caches
        if($this->ws->memcached_enabled)
        {
          $this->ws->invalidateCache('auth-validator');
          $this->ws->invalidateCache('auth-lister:dataset');
          $this->ws->invalidateCache('auth-lister:ws');
          $this->ws->invalidateCache('auth-lister:groups');
          $this->ws->invalidateCache('auth-lister:group_users');
          $this->ws->invalidateCache('auth-lister:access_user');
          $this->ws->invalidateCache('auth-lister:access_dataset');
          $this->ws->invalidateCache('auth-lister:access_group');
          $this->ws->invalidateCache('crud-read');
          $this->ws->invalidateCache('dataset-read');
          $this->ws->invalidateCache('dataset-read:all');
          $this->ws->invalidateCache('revision-read');
          $this->ws->invalidateCache('revision-lister');
          $this->ws->invalidateCache('search');
          $this->ws->invalidateCache('sparql');   
        }

      /*      
            if(!$solr->optimize())
            {
              $this->ws->conneg->setStatus(500);
              $this->ws->conneg->setStatusMsg("Internal Error");
              $this->ws->conneg->setStatusMsgExt("Error #dataset-delete-104");  
              return;          
            }      
      */
      }      
    }
  }
?>
