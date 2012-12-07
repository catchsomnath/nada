<style>
	.dashboard-box{border:1px solid gainsboro; -moz-border-radius: 5px;	-webkit-border-radius: 5px; color:#333333}
	.dashboard-box-title{font-size:16px; text-transform:uppercase;padding:5px;background:gainsboro}
	.dashboard-box-body{padding:5px;}
	.dashboard-box-footer{padding:5px;font-size:12px;}
	.dashboard-box-spacer{height:10px;}
	.dashboard-box a{color:#000066;text-decoration:none; font-weight:normal;}
	.dashboard-box a:hover{color:maroon;}
	.users .user{font-style:italic;}
</style>
<div class="content-container">
<h1><?php echo t('dashboard');?></h1>
<div class="yui-g row-fluid">
	
    <?php if (isset($news)):?>
    <div class="yui-u first span8" style="border-right:1px solid gainsboro;">		
        	<div class="dashboard-box">
            	<div class="dashboard-box-title"><?php echo t('nada_news_updates');?></div>
                <div class="dashboard-box-body"><?php echo $news;?></div>
                <div class="dashboard-box-footer"></div>
            </div>        	        
	</div>
    <?php endif;?>
    
    <?php if (isset($recent_studies)):?>
    <div class="yui-u first span6" >		
        	<div class="dashboard-box">
            	<div class="dashboard-box-title"><?php echo t('recent_studies');?></div>
                <div class="dashboard-box-body">
	                <?php $tr_class=""; ?>
                	<table style="width:100%;" class="grid-table">
					<?php foreach($recent_studies as $row):?>
                    <?php if($tr_class=="") {$tr_class="alternate";} else{ $tr_class=""; } ?>
                    	<tr class="<?php echo $tr_class;?>">
						<td><?php echo strtoupper($row['repositoryid']);?></td>
                        <td><?php echo anchor('catalog/'.$row['id'],$row['titl']);?></td>
                         <td><?php echo relative_date($row['changed']); ?></td>
                        </tr>
                    <?php endforeach;?>
                    </table>
                </div>
                <div class="dashboard-box-footer"></div>
            </div>        	        
	</div>
    <?php endif;?>
    
	<div class="yui-u span6">
                <div class="dashboard-box">
                    <div class="dashboard-box-title"><?php echo t('users');?></div>
                    <div class="dashboard-box-body">
                    <?php if (isset($user_stats)):?>
                    	<div>
                    	<div><?php echo $user_stats['active']; ?> <?php echo t('user_active');?> </div>
                        <div><?php echo $user_stats['disabled']; ?> <?php echo t('user_disabled');?> </div>
                        <div><?php echo $user_stats['inactive']; ?> <?php echo t('user_inactive');?></div>
                        <div><?php echo $user_stats['anonymous_users']; ?> <?php echo t('anonymous_users');?></div>
                        </div>
                        <div class="users">
	                        	<?php echo count($user_stats['loggedin_users']);?> <?php echo t('logged_in_users');?>:
                            	<span class="user"><?php echo implode(', ',$user_stats['loggedin_users'])?></span>
                        </div>
                    <?php endif;?>
                    </div>
                </div>
                <div class="dashboard-box-spacer"></div>
                <div class="dashboard-box">
                    <div class="dashboard-box-title"><?php echo t('database_backup');?></div>
                    <div class="dashboard-box-body">
                        <p><a href="<?php echo site_url(); ?>/backup/create/"><?php echo t('run_database_backup_script');?></a></p>
                    </div>
                </div>           
                <div class="dashboard-box-spacer"></div>
                <div class="dashboard-box">
                    <div class="dashboard-box-title"><?php echo t('cache_files');?></div>
                    <div class="dashboard-box-body">
                    	<?php if (isset($cache_files)):?>
                        <?php if ($cache_files>0):?>
                        	<?php echo sprintf (t("clear_cache_files"),$cache_files,site_url().'/admin/clear_cache/');?>
                            <?php else:?>
                            <p><?php echo t('no_cache_files_found');?></p>
                        <?php endif;?>
                        <?php endif;?>
                    </div>											
                </div>           

                <div class="dashboard-box-spacer"></div>
                <div class="dashboard-box">
                    <div class="dashboard-box-title"><?php echo t('bug_report');?></div>
                    <div class="dashboard-box-body">
                        <p>&nbsp;</p>
                    	<?php if (isset($this->error)):?>
                        	<div class="error">
                                <?php echo $this->error; ?>
                            </div>
						<?php endif;?>
                        <?php if (validation_errors() ) : ?>
                            <div class="error">
                                <?php echo validation_errors(); ?>
                            </div>
                        <?php endif; ?>
                    	<?php $error=$this->session->flashdata('error');?>
						<?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>
						<?php $message=$this->session->flashdata('message');?>
                        <?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>

                    	<form class="form" method="post" action="<?php echo site_url().'/admin';?>">
                        	<div class="field">
						        <label for="name"><?php echo t('reporter_name');?><span class="required">*</span></label>
						        <input class="input-flex" name="name" type="text" id="name"  value="<?php echo get_form_value('name',isset($name) ? $name : ''); ?>"/>
						    </div>

                        	<div class="field">
						        <label for="email"><?php echo t('reporter_email');?><span class="required">*</span></label>
						        <input class="input-flex" name="email" type="text" id="email"  value="<?php echo get_form_value('email',isset($email) ? $email : ''); ?>"/>
						    </div>

                        	<div class="field">
						        <label for="subject"><?php echo t('subject');?><span class="required">*</span></label>
						        <input class="input-flex" name="subject" type="text" id="subject"  value="<?php echo get_form_value('subject',isset($subject) ? $subject : ''); ?>"/>
						    </div>

                            <div class="field">
                                <label for="body"><?php echo t('bug_request_description');?><span class="required">*</span></label>
                                <textarea class="input-flex"  name="body" rows="10"><?php echo get_form_value('body',isset($body) ? $body : ''); ?></textarea>
                            </div>
                            
							<?php echo form_submit('submit_bug',t('submit')); ?>
                            
                        </form>
                    </div>											
                </div>           


	</div>
</div>