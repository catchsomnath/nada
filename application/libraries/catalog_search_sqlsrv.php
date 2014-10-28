<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Data Catalog Search Class for MYSQL FULLTEXT Database
 * 
 * 
 *
 * @package		NADA 3
 * @subpackage	Libraries
 * @category	Data Catalog Search
 * @author		Mehmood Asghar
 *
 */ 
class Catalog_search{
	
	var $ci;
	
	var $errors=array();
	
	//search fields
	var $study_keywords='';
	var $variable_keywords='';
	var $variable_fields=array();
	var $topics=array();
	var $countries=array();
	var $from=0;
	var $to=0;
	var $repo='';
	var $dtype=array();//data access type
	var $collections=array();

	//allowed variable search fields
	var $variable_allowed_fields=array('labl','name','qstn','catgry');
	
	//allowed sort options
	var $sort_allowed_fields=array(
						'titl'=>'titl',
						'nation'=>'nation',
						'proddate'=>'proddate',
						'popularity'=>'total_views',
						'collection'=>'repositories.repositoryid');
	var	$sort_allowed_order=array('asc','desc');
	
	//default sort
	var $sort_by='titl';
	var $sort_order='ASC';
	var $use_fulltext=TRUE;
		
    /**
	 * Constructor
	 *
	 * @access	public
	 * @param	array	initialization parameters
	 */
	function __construct($params = array())
	{
		$this->ci=& get_instance();
		
		//change default sort if regional search is ON
		if ($this->ci->config->item("regional_search")=='yes')
		{
			$this->sort_by='nation';
		}
		
		if (count($params) > 0)
		{
			$this->initialize($params);
		}
		
		//use fulltext or not
		if ($this->ci->config->item("sqlsrv_use_fulltext")===TRUE)
		{
			$this->use_fulltext=TRUE;
		}
		else
		{
			$this->use_fulltext=FALSE;
		}
		//log_message('debug', "Catalog_search Class Initialized");
		//$this->ci->output->enable_profiler(TRUE);
	}
	
	function initialize($params=array())
	{
		if (count($params) > 0)
		{
			foreach ($params as $key => $val)
			{
				if (isset($this->$key))
				{
					$this->$key = $val;
				}
			}
		}
	}


	//build the search query
	function _search_count($limit=15, $offset=0)
	{
		$dtype=$this->_build_dtype_query();
		$study=$this->_build_study_query();
		$variable=$this->_build_variable_query();
		$topics=$this->_build_topics_query();
		$countries=$this->_build_countries_query();
		$years=$this->_build_years_query();
		$repository=$this->_build_repository_query();
		$collections=$this->_build_collections_query();
		
		$sort_by=array_key_exists($this->sort_by,$this->sort_allowed_fields) ? $this->sort_allowed_fields[$this->sort_by] : 'nation';
		$sort_order=in_array($this->sort_order,$this->sort_allowed_order) ? $this->sort_order : 'ASC';		
		$sort_options[0]=$sort_options[0]=array('sort_by'=>$sort_by, 'sort_order'=>$sort_order);
						
		//array of all options
		$where_list=array($study,$variable,$topics,$countries,$years,$dtype,$collections);
		
		if ($repository!='')
		{
			$where_list[]=$repository;
		}
		
		//show only publshed studies
		$where_list[]='published=1';
		
		//create combined where clause
		$where='';
		
		foreach($where_list as $stmt)
		{
			if ($where=='')
			{
				$where=$stmt;
			}
			else
			{
				if ($stmt!==FALSE) {
					$where.="\r\n".' AND '. $stmt;
				}
			}
		}

		//study fields returned by the select statement
		$study_fields='surveys.id,refno,surveyid,titl,nation,authenty, f.model as form_model,link_report,link_indicator, link_questionnaire, link_technical, link_study,proddate, isshared, repositoryid,created,data_coll_start,data_coll_end';

		//build final search sql query
		$sql='';
		$sql_array=array();
		
		if ($variable!==FALSE)
		{
			//variable search			
			/*$this->ci->db->select('count(*) as rowsfound',FALSE);
			$this->ci->db->from('surveys');
			$this->ci->db->join('forms f','surveys.formid=f.formid','left');
			$this->ci->db->join('variables v','surveys.id=v.surveyid_fk','inner');
			$this->ci->db->group_by('id,refno,surveyid,titl,nation,authenty, f.model,link_report,link_indicator, link_questionnaire, link_technical, link_study,proddate, isshared, repositoryid,varcount');
			*/
			$query_count='select count(rowsfound) as rowsfound from (';
			$query_count.='select count(surveys.id) as rowsfound from surveys ';
			$query_count.='inner join variables v on v.surveyid_FK=surveys.id ';
			$query_count.='left join forms f on f.formid=surveys.formid ';
			
			if ($repository!='')
			{
				$query_count.='left join survey_repos on surveys.id=survey_repos.sid ';
			}
			
			if ($where!='')
			{
				$query_count.='where '.$where;
			}
			$query_count.=" GROUP BY surveys.id,surveys.refno,surveys.surveyid,surveys.titl,surveys.nation,surveys.authenty, f.model,link_report,link_indicator, link_questionnaire, link_technical, link_study,proddate, isshared, surveys.repositoryid,varcount \r\n";
			$query_count.=') as result';
			
			//$query_found_rows=$this->query($query_count)->row_array();
		
			
			/*if ($where!='') 
			{
				$this->ci->db->where($where);
			}*/

			//group by
			//$sql.=" GROUP BY id,refno,surveyid,titl,nation,authenty, f.model,link_report,link_indicator, link_questionnaire, link_technical, link_study,proddate, isshared, repositoryid,varcount \r\n";

			//$query=$this->ci->db->get();
			$query=$this->ci->db->query($query_count);
		}		
		else 
		{
			//study search
			$this->ci->db->select(" count(surveys.id) as rowsfound ",FALSE);
			$this->ci->db->from('surveys');
			$this->ci->db->join('forms f','surveys.formid=f.formid','left');
			if ($repository!='')
			{
				$this->ci->db->join('survey_repos','surveys.id=survey_repos.sid','left');
			}
			
			if ($where!='') 
			{
				$this->ci->db->where($where);
			}
		
			$query=$this->ci->db->get();
		}
		
		if ($query)
		{
			//result to array
			$rows_found=$query->row_array();
			if ($rows_found)
			{
				return $rows_found['rowsfound'];
			}
		}
		return FALSE;
	}


	//perform the search
	function search($limit=15, $offset=0)
	{
		$dtype=$this->_build_dtype_query();
		$study=$this->_build_study_query();
		$variable=$this->_build_variable_query();
		$topics=$this->_build_topics_query();
		$countries=$this->_build_countries_query();
		$years=$this->_build_years_query();
		$repository=$this->_build_repository_query();
		$collections=$this->_build_collections_query();
		
		$sort_by=array_key_exists($this->sort_by,$this->sort_allowed_fields) ? $this->sort_allowed_fields[$this->sort_by] : 'nation';
		$sort_order=in_array($this->sort_order,$this->sort_allowed_order) ? $this->sort_order : 'ASC';
		
		$sort_options[0]=$sort_options[0]=array('sort_by'=>$sort_by, 'sort_order'=>$sort_order);
		
		//multi-column sort
		if ($sort_by=='nation')
		{
			$sort_options[1]=array('sort_by'=>'proddate', 'sort_order'=>'desc');
			$sort_options[2]=array('sort_by'=>'titl', 'sort_order'=>'asc');
		}
		elseif ($sort_by=='titl')
		{
			$sort_options[1]=array('sort_by'=>'proddate', 'sort_order'=>'desc');
			$sort_options[2]=array('sort_by'=>'nation', 'sort_order'=>'asc');
		}
		if ($sort_by=='proddate')
		{
			$sort_options[2]=array('sort_by'=>'nation', 'sort_order'=>'asc');
			$sort_options[2]=array('sort_by'=>'titl', 'sort_order'=>'asc');
		}
		
		
		if (trim($this->study_keywords)!=='')
		{
			//array_unshift($sort_options,array('sort_by'=>'k.rank', 'sort_order'=>'desc') );
			$sort_options[]=array('sort_by'=>'k.rank', 'sort_order'=>'desc');
		}
		

		//array of all options
		$where_list=array($study,$variable,$topics,$countries,$years,$repository,$dtype,$collections);
		
		//create combined where clause
		$where='';
		
		foreach($where_list as $stmt)
		{
			if ($where=='')
			{
				$where=$stmt;
			}
			else
			{
				if ($stmt!==FALSE) {
					$where.="\r\n".' AND '. $stmt;
				}
			}
		}
		
		//study fields returned by the select statement
		$study_fields='surveys.id as id,refno,surveys.surveyid as surveyid,titl,nation,authenty, f.model as form_model,link_report,surveys.data_coll_start,surveys.data_coll_end';
		$study_fields.=',link_indicator, link_questionnaire, link_technical, link_study,proddate';
		$study_fields.=', isshared, surveys.repositoryid as repositoryid, link_da, repositories.title as repo_title, surveys.created,surveys.changed,surveys.total_views,surveys.total_downloads';

		//build final search sql query
		$sql='';
		$sql_array=array();
		
		if ($variable!==FALSE)
		{
			//variable search			
			$this->ci->db->select($study_fields.',varcount, count(*) as var_found',FALSE);
			$this->ci->db->from('surveys');
			$this->ci->db->join('forms f','surveys.formid=f.formid','left');
			$this->ci->db->join('variables v','surveys.id=v.surveyid_fk','inner');
			$this->ci->db->join('repositories','surveys.repositoryid=repositories.repositoryid','left');
			$this->ci->db->where('surveys.published',1);
			
			if ($repository!='')
			{
				$this->ci->db->join('survey_repos','surveys.id=survey_repos.sid','left');
			}
			
			$this->ci->db->group_by('surveys.id,surveys.refno,surveys.surveyid,surveys.titl,surveys.nation,surveys.authenty, f.model,link_report,link_indicator, link_questionnaire, surveys.link_da, link_technical, link_study,proddate, surveys.isshared, surveys.repositoryid,varcount, repositories.title, surveys.created,surveys.data_coll_start,surveys.data_coll_end,surveys.changed,surveys.total_views,surveys.total_downloads');
			
			if (trim($this->study_keywords)!=='')
			{
				$this->ci->db->group_by('k.rank');
			}

			if ($where!='') 
			{
				$this->ci->db->where($where);
			}

			//multi-sort
			$sql_sorts=array();
			foreach($sort_options as $sort)
			{
				//$sql_sorts[]=sprintf(' %s %s', $sort['sort_by'],$sort['sort_order']);
				$this->ci->db->order_by($sort['sort_by'],$sort['sort_order']);
			}
			
			/*if (count($sql_sorts)>0)
			{			
				$sql.=sprintf(' ORDER BY %s', implode(", ", $sql_sorts));
			}*/
			
			$this->ci->db->limit($limit,$offset);			
			$query=$this->ci->db->get();
		}		
		else 
		{
			//study search
			$this->ci->db->select(" $study_fields ",FALSE);
			$this->ci->db->from('surveys');
			$this->ci->db->join('forms f','surveys.formid=f.formid','left');
		//	$this->ci->db->join('harvester_queue hq','surveys.surveyid=hq.surveyid AND surveys.repositoryid=hq.repositoryid','left');
			$this->ci->db->join('repositories','surveys.repositoryid=repositories.repositoryid','left');
			$this->ci->db->where('surveys.published',1);
			
			if ($repository!='')
			{
				$this->ci->db->join('survey_repos','surveys.id=survey_repos.sid','left');
			}

			//multi-sort
			foreach($sort_options as $sort)
			{
				$this->ci->db->order_by($sort['sort_by'],$sort['sort_order']);
			}

			$this->ci->db->limit($limit,$offset);
			
			if ($where!='') 
			{
				$this->ci->db->where($where);
			}
		
			$query=$this->ci->db->get();
		}
		
		if ($query)
		{
			//result to array
			$this->search_result=$query->result_array();
		}
		else
		{
			//some error occured
			return FALSE;
		}
		
		//get total search result count		
		$this->search_found_rows=$this->_search_count();
		
		//get total surveys in db
		$this->ci->db->select('count(surveys.id) as rowsfound',FALSE);
		$this->ci->db->where('published',1);
		
		if ($repository!='')
		{			
			$this->ci->db->join('survey_repos','surveys.id=survey_repos.sid','left');
			$this->ci->db->where('survey_repos.repositoryid',(string)$this->repo);
		}

		$query_total_surveys=$this->ci->db->get('surveys')->row_array();
		//$query_total_surveys=$this->ci->db->query(sprintf('SELECT count(*) as rowsfound from %ssurveys', $this->ci->db->dbprefix))->row_array();
		$this->total_surveys=$query_total_surveys['rowsfound'];

		//combine into one array
		$result['rows']=$this->search_result;
		$result['found']=$this->search_found_rows;
		$result['total']=$this->total_surveys;
		$result['limit']=$limit;
		$result['offset']=$offset;
		$result['citations']=$this->get_survey_citation();
		return $result;
	}
	
	/**
	* Build study search
	*/
	function _build_study_query()
	{
		//study search keywords
		$study_keywords=$this->study_keywords;

		if ($study_keywords=='')
		{
			return FALSE;
		}

		//fix quotes, nada escapes quote
		//$study_keywords=str_replace('&quot;','',$study_keywords);
		//$study_keywords=str_replace('"','',$study_keywords);
		//$study_keywords='"'.$study_keywords.'"';

		//use freetext search if has no +-s
		if (strpos($study_keywords," +")!==FALSE || strpos($study_keywords," -")!==FALSE)
		{
			$bool_search=array();
			$keywords=preg_replace(array('/\s\+\s/','/\s\-\s/'),array(' +',' -'),$study_keywords);
			$keywords = preg_replace('/\s\s+/', ' ', $keywords);

			$keywords_arr=explode(" ",$keywords);
			
			$output=array();
			$op="AND";
			foreach($keywords_arr as $word)
			{
				if (trim($word)=="")
				{
					continue;
				}
				
				if (substr($word,0,1)=='+')
				{
					$op="AND";
					$output[$op][]=substr($word,1,strlen($word));
				}
				else if (substr($word,0,1)=='-')
				{
					$op="NOT";
					$output[$op][]=substr($word,1,strlen($word));
				}
				else
				{
					$output[$op][]=$word;
				}	
			}
			
			//for searches such as -national -education change them to national and education
			if (!isset($output['AND']) && isset($output['NOT']))
			{
				$output['AND']=$output['NOT'];
				unset($output['NOT']);
			}
			
			$query= implode(" AND ",$output['AND']);
			
			//both AND and NOT are searched
			if (isset($output['NOT']) && isset($output['AND']))
			{
				$query.= ' AND NOT '.'('.implode(" OR ",$output['NOT']).')';
			}
			
			$this->ci->db->join("containstable(surveys, (ft_keywords), ".$this->ci->db->escape($query).") k","surveys.id=k.[key]","INNER");
			
		}
		else //freetext query
		{
			$this->ci->db->join("freetexttable(surveys, (ft_keywords), ".$this->ci->db->escape($study_keywords).") k","surveys.id=k.[key]","INNER");		
		}

	}
	
			
	function _build_variable_query()
	{
		$variable_keywords=trim($this->variable_keywords);		
		$variable_fields=$this->variable_fields();		//cleaned list of variable fields array

		if ($variable_keywords=='')
		{
			return FALSE;
		}
		
		//$variable_keywords=str_replace('&quot;','',$variable_keywords);
		//$variable_keywords=str_replace('"','',$variable_keywords);
		//$variable_keywords='"'.$variable_keywords.'"';

		if ($this->use_fulltext)
		{
			$fulltext_index=$this->get_variable_search_field(TRUE);
			return sprintf("( freetext((%s),%s) )",$fulltext_index,$this->ci->db->escape($variable_keywords));
		}
			
		$tmp_where=NULL;
		$keyword_list=explode(" ", $variable_keywords);
		
		foreach($keyword_list as $keyword)
		{
			if (strlen($keyword) >=3)
			{
				//get fulltext index name
				//$fulltext_index=$this->get_variable_search_field(TRUE);
				
				//wild card search
				$tmp_where[]=sprintf('v.labl like %s',$this->ci->db->escape('%'.$keyword.'%'));
				$tmp_where[]=sprintf('v.name like %s',$this->ci->db->escape('%'.$keyword.'%'));
				$tmp_where[]=sprintf('v.qstn like %s',$this->ci->db->escape('%'.$keyword.'%'));
				$tmp_where[]=sprintf('v.catgry like %s',$this->ci->db->escape('%'.$keyword.'%'));
			}	
		}	
				
		if ($tmp_where!=NULL)
		{
			return '('.implode(' OR ',$tmp_where).')';
		}
		
		return FALSE;
	}
	
	/**
	*
	* build where for topics
	*/
	function _build_topics_query()
	{
		$topics=$this->topics;//must always be an array

		if (!is_array($topics))
		{
			return FALSE;
		}
		
		//remove topics that are not numeric
		$topics_clean=array();
		foreach($topics as $topic)
		{
			if (is_numeric($topic) )
			{
				$topics_clean[]=$topic;
			}	
		}
		
		if ( count($topics_clean)>0)
		{
			$topics=implode(',',$topics_clean);
		}
		else
		{
			return FALSE;
		}
		
		//topics
		if ($topics!='')
		{
			return sprintf('surveys.id in (select sid from survey_topics where tid in (%s) )',$topics);
		}
		
		return FALSE;
	}
	

	/**
	*
	* build where for nations
	*/
	function _build_countries_query()
	{
		$countries=$this->countries;//must always be an array

		if (!is_array($countries))
		{
			return FALSE;
		}
		
		$countries_list=array();

		foreach($countries  as $country)
		{
			//escape country names for db
			$countries_list[]=$this->ci->db->escape($country);
		}

		if ( count($countries_list)>0)
		{
			$countries= implode(',',$countries_list);
		}
		else
		{
			return FALSE;
		}

		//countries
		if ($countries!='')
		{
			return sprintf('surveys.id in (select sid from survey_countries where cid in (%s))',$countries);
		}
		
		return FALSE;
	}
	
	
	function _build_years_query()
	{
		$from=(integer)$this->from;
		$to=(integer)$this->to;
		
		if ($from>0 && $to>0)
		{
			return sprintf('surveys.id in (select sid from survey_years where (data_coll_year between %s and %s) or (data_coll_year=0) )',$from, $to);
		}
		
		return FALSE;
	}
	
	
	function _build_dtype_query()
	{
		$dtypes=$this->dtype;

		if (!is_array($dtypes) || count($dtypes)<1)
		{
			return FALSE;
		}

		foreach($dtypes as $key=>$value)
		{
			if (!is_numeric($value))
			{
				unset($dtypes[$key]);
			}
		}
		
		$types_str=implode(",",$dtypes);

		if ($types_str!='')
		{
			return sprintf(' f.formid in (%s)',$types_str);
		}
		
		return FALSE;	
	}
	
		
	/**
	*
	* Get the fulltext index or concatenated fields for searching for variables
	*/	
	function get_variable_search_field($is_fulltext=TRUE)
	{
		$index=NULL;
		
		$variable_fields=$this->variable_fields();
		
		//select which index to use
		if( in_array('name',$variable_fields) )
		{
			$index[]='name';
		}
		if( in_array('labl',$variable_fields) )
		{
			$index[]='labl';
		}
		if( in_array('qstn',$variable_fields) )
		{
			$index[]='qstn';
		}
		if( in_array('catgry',$variable_fields) )
		{
			$index[]='catgry';
		}
		
		if ($index==NULL)
		{
			$index[]='name,labl,qstn';
		}

		if ($is_fulltext==TRUE)	
		{
			//fulltext
			return implode(',',$index);
		}
		else
		{	
			//concatenated fields
			return 'concat('.implode(',',$index).')';
		}
	}

	/*
	* setup variable fields 
	*
	* Note: sets a variable field to search on. If no field selected by the user, selects the default field.
	* 		the function will always return an array of variable field(s)
	*/
	function variable_fields()
	{
		$vf=$this->variable_fields;
		
		if (!is_array($vf))
		{
			//default search field if nothing is selected
			return array('label');
		}
		
		$tmp=NULL;
		foreach($vf as $field)
		{
			if (in_array($field,$this->variable_allowed_fields))
			{
				$tmp[]=$field;
			}
		}
	
		//no allowed fields found	
		if ($tmp==NULL)
		{
			return array('label');
		}
		else
		{
			return $tmp;
		}		
	}

	/**
	*
	* returns an array of surveys with citations
	*
	*/
	function get_survey_citation()
	{
		if (!is_array($this->search_result))
		{
			return FALSE;
		}
		else if (count($this->search_result)==0)
		{
			return FALSE;
		}
		
		//build a list of survey IDs
		foreach($this->search_result as $row)
		{
			if (is_numeric($row['id']))
			{
				$survey_id_list[]=$row['id'];
			}	
		}
		
		$surveys=implode(',',$survey_id_list);
		$this->ci->db->select('sid,count(sid) as total');	
		$this->ci->db->where("sid in ($surveys)");
		$this->ci->db->group_by('sid');	
		$query=$this->ci->db->get('survey_citations');
		
		if ($query)
		{
			$citation_rows=$query->result_array();
						
			$result=array();
			
			foreach($citation_rows as $row)
			{
				$result[$row['sid']]=$row['total'];
			}
			return $result;
		}
		
		return FALSE;
	}

	//search on variables
	function vsearch($limit = 15, $offset = 0)
	{
		//sort allowed fields for the variable view
		$sortable_fields=array('name','labl','titl','nation');

		$sort_by=in_array($this->sort_by,$sortable_fields) ? $this->sort_by : 'titl';
		$sort_order=in_array($this->sort_order,$this->sort_allowed_order) ? $this->sort_order : 'ASC';

		$variable_keywords=$this->variable_keywords;
		$variable_fields=$this->variable_fields;

		$study=$this->_build_study_query();
		$variable=$this->_build_variable_query();
		$topics=$this->_build_topics_query();
		$countries=$this->_build_countries_query();
		$years=$this->_build_years_query();
		$dtype=$this->_build_dtype_query();
		
		//array of all options
		$where_list=array($study,$variable,$topics,$countries,$years,$dtype);
		
		//create combined where clause
		$where='';
		
		foreach($where_list as $stmt)
		{
			if ($where=='')
			{
				$where=$stmt;
			}
			else
			{
				if ($stmt!==FALSE) {
					$where.="\r\n".' AND '. $stmt;
				}
			}
		}

		if ($where=='') {
			return FALSE;
		}

		//echo $where;exit;
		
		//search
		$this->ci->db->limit($limit, $offset);		
		$this->ci->db->select("v.uid,v.name,v.labl,v.varID,  surveys.titl as titl,surveys.nation as nation, v.surveyid_FK",FALSE);
		$this->ci->db->join('surveys', 'v.surveyid_fk = surveys.id','inner');	
		$this->ci->db->join('forms','surveys.formid=forms.formid','left');
		$this->ci->db->order_by($sort_by, $sort_order); 
		$this->ci->db->where($where);
		
		//get resultset
		$result=$this->ci->db->get("variables as v")->result_array();
		
		//get total search result count
		/*
		$this->ci->db->select("count(*) as rowsfound",FALSE);
		$this->ci->db->join('surveys', 'v.surveyid_fk = surveys.id','inner');	
		$this->ci->db->where($where);		
		$query_found_rows=$this->ci->db->get("variables as v")->row_array();
		*/
		
		//$query_count='select count(*) as rowsfound (';
		$query_count='select count(*) as rowsfound from surveys ';
		$query_count.='inner join variables v on v.surveyid_FK=surveys.id ';
		if ($where!='')
		{
			$query_count.=' where '.$where;
		}	
		//$query_count.=') as result';
		//var_dump($query_count);exit;
		$query_found_rows=$this->ci->db->query($query_count)->row_array();
		//var_dump($query_found_rows);exit;
		//->row_array();
		
		$found_rows=$query_found_rows['rowsfound'];

		//return $result;
		$tmp['total']=$this->ci->db->count_all('variables');
		$tmp['found']=$found_rows;
		$tmp['limit']=$limit;
		$tmp['offset']=$offset;
		$tmp['rows']=$result;
		return $tmp;		
	}

	//search for variables for a single survey
	function v_quick_search($surveyid=NULL,$limit=50,$offset=0)
	{
		//sort allowed fields for the variable view
		$sortable_fields=array('name','labl','nation');

		$sort_by=in_array($this->sort_by,$sortable_fields) ? $this->sort_by : 'name';
		$sort_order=in_array($this->sort_order,$this->sort_allowed_order) ? $this->sort_order : 'ASC';

		$variable_keywords=$this->variable_keywords;
		$variable_fields=$this->variable_fields;

		$variable=$this->_build_variable_query();
				
		//array of all options
		$where_list=array($variable);
		
		//create combined where clause
		$where='';
		
		foreach($where_list as $stmt)
		{
			if ($where=='')
			{
				$where=$stmt;
			}
			else
			{
				if ($stmt!==FALSE) {
					$where.="\r\n".' AND '. $stmt;
				}
			}
		}
		
		if ($where=='') {
			return FALSE;
		}

		//echo $where;exit;
		
		//search
		$this->ci->db->limit($limit, $offset);		
		$this->ci->db->select("v.uid,v.name,v.labl,v.varID,v.qstn");
		$this->ci->db->order_by($sort_by, $sort_order); 
		$this->ci->db->where($where);
		$this->ci->db->where('surveyid_fk',$surveyid);
		
		//get resultset
		$query=$this->ci->db->get("variables as v");

		if ($query)
		{
			return $query->result_array();
		}
		else
		{
			return FALSE;
		}
	}

	function _build_repository_query()
	{
		$repo=(string)$this->repo;

		if ($repo!='')
		{
			return sprintf('survey_repos.repositoryid = %s',$this->ci->db->escape($repo));
		}
		return FALSE;
	}


	function _build_collections_query()
	{	
		$params=$this->collections;//must always be an array

		if (!is_array($params))
		{
			return FALSE;
		}
		
		$param_list=array();

		foreach($params  as $param)
		{
			//escape country names for db
			$param_list[]=$this->ci->db->escape($param);
		}

		if ( count($param_list)>0)
		{
			$params= implode(',',$param_list);
		}
		else
		{
			return FALSE;
		}

		if ($param!='')
		{
			return sprintf('surveys.id in (select sid from survey_repos where repositoryid in (%s) )',$params);
		}
		
		return FALSE;	
	}
}// END Search class

/* End of file Catalog_search.php */
/* Location: ./application/libraries/Catalog_search.php */