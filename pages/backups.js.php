<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @file      backups.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$superGlobal = new SuperGlobal();
$lang = new Language(); 

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => isset($_POST['type']) === true ? htmlspecialchars($_POST['type']) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('backups') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    $(document).on('click', '.key-generate', function() {
        $.post(
            "sources/main.queries.php", {
                type: "generate_password",
                type_category: 'action_user',
                size: "<?php echo $SETTINGS['pwd_maximum_length']; ?>",
                lowercase: "true",
                numerals: "true",
                capitalize: "true",
                symbols: "false",
                secure: "true",
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

                if (data.key !== "") {
                    $('#onthefly-backup-key').val(data.key);
                }
            }
        );
    });

    $(document).on('click', '.btn-choose-file', function() {
        $('#onthefly-restore-progress, #onthefly-backup-progress')
            .addClass('hidden')
            .html('');
    });

    $(document).on('click', '.start', function() {
        var action = $(this).data('action');

        if (action === 'onthefly-backup') {
            // PERFORM ONE BACKUP
            if ($('#onthefly-backup-key').val() !== '') {
                // Show cog
                toastr.remove();
                toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'encryptionKey': simplePurifier($('#onthefly-backup-key').val()),
                };

                //send query
                $.post(
                    "sources/backups.queries.php", {
                        type: "onthefly_backup",
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                        console.log(data);

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.error(
                                '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                                '<?php echo $lang->get('error'); ?>', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        } else {
                            // Store KEY in DB
                            var newData = {
                                "field": 'bck_script_passkey',
                                "value": simplePurifier($('#onthefly-backup-key').val()),
                            }

                            $.post(
                                "sources/admin.queries.php", {
                                    type: "save_option_change",
                                    data: prepareExchangedData(JSON.stringify(newData), "encode", "<?php echo $session->get('key'); ?>"),
                                    key: "<?php echo $session->get('key'); ?>"
                                },
                                function(data) {
                                    // Handle server answer
                                    try {
                                        data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                                    } catch (e) {
                                        // error
                                        toastr.remove();
                                        toastr.error(
                                            '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                                            '<?php echo $lang->get('error'); ?>', {
                                                timeOut: 5000,
                                                progressBar: true
                                            }
                                        );
                                        return false;
                                    }

                                    if (data.error === false) {
                                        toastr.remove();
                                        toastr.success(
                                            '<?php echo $lang->get('done'); ?>',
                                            '', {
                                                timeOut: 1000
                                            }
                                        );
                                    }
                                }
                            );
                            // SHOW LINK
                            $('#onthefly-backup-progress')
                                .removeClass('hidden')
                                .html('<div class="alert alert-success alert-dismissible ml-2">' +
                                    '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                                    '<h5><i class="icon fa fa-check mr-2"></i><?php echo $lang->get('done'); ?></h5>' +
                                    '<i class="fas fa-file-download mr-2"></i><a href="' + data.download + '"><?php echo $lang->get('pdf_download'); ?></a>' +
                                    '</div>');

                            // Inform user
                            toastr.remove();
                            toastr.success(
                                '<?php echo $lang->get('done'); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );
                        }
                    }
                );

            }
        } else if (action === 'onthefly-restore') {
            // PERFORM A RESTORE
            if ($('#onthefly-restore-key').val() !== '') {
                // Show cog
                toastr.remove();
                toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'encryptionKey': simplePurifier($('#onthefly-restore-key').val()),
                    'backupFile': $('#onthefly-restore-file').data('operation-id')
                };
                console.log(data);
                //send query
                $.post(
                    "sources/backups.queries.php", {
                        type: "onthefly_restore",
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                        console.log(data);

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.error(
                                '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                                '<?php echo $lang->get('error'); ?>', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        } else {
                            // SHOW LINK
                            $('#onthefly-restore-progress')
                                .removeClass('hidden')
                                .html('<div class="alert alert-success alert-dismissible ml-2">' +
                                    '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                                    '<h5><i class="icon fa fa-check mr-2"></i><?php echo $lang->get('done'); ?></h5>' +
                                    '<?php echo $lang->get('restore_done_now_logout'); ?>' +
                                    '</div>');

                            // Inform user
                            toastr.remove();
                            toastr.success(
                                '<?php echo $lang->get('done'); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );
                        }
                    }
                );
            }
        }
    });



    // PREPARE UPLOADER with plupload
    var restoreOperationId = '',
        uploader_restoreDB = new plupload.Uploader({
            runtimes: "gears,html5,flash,silverlight,browserplus",
            browse_button: "onthefly-restore-file-select",
            container: "onthefly-restore-file",
            max_file_size: '<?php
            if (strrpos($SETTINGS['upload_maxfilesize'], 'mb') === false) {
                echo $SETTINGS['upload_maxfilesize'] . 'mb';
            } else {
                echo $SETTINGS['upload_maxfilesize'];
            }
            ?>',
            chunk_size: '5mb',
            unique_names: true,
            dragdrop: true,
            multiple_queues: false,
            multi_selection: false,
            max_file_count: 1,
            url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.files.php",
            flash_swf_url: "<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/plupload/js/plupload.flash.swf",
            silverlight_xap_url: "<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/plupload/js/plupload.silverlight.xap",
            filters: [{
                title: "SQL files",
                extensions: "sql"
            }],
            init: {
                FilesAdded: function(up, files) {
                    // generate and save token
                    $.post(
                        "sources/main.queries.php", {
                            type: "save_token",
                            type_category: 'action_system',
                            size: 25,
                            capital: true,
                            numeric: true,
                            ambiguous: true,
                            reason: "restore_db",
                            duration: 10,
                            key: '<?php echo $session->get('key'); ?>'
                        },
                        function(data) {
                            console.log(data);
                            store.update(
                                'teampassUser',
                                function(teampassUser) {
                                    teampassUser.uploadToken = data[0].token;
                                }
                            );
                            up.start();
                        },
                        "json"
                    );
                },
                BeforeUpload: function(up, file) {
                    // Show cog
                    toastr.remove();
                    toastr.info('<?php echo $lang->get('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
                    console.log("Upload token: "+store.get('teampassUser').uploadToken);

                    up.setOption('multipart_params', {
                        PHPSESSID: '<?php echo $session->get('user-id'); ?>',
                        type_upload: 'restore_db',
                        File: file.name,
                        user_token: store.get('teampassUser').uploadToken
                    });
                },
                UploadComplete: function(up, files) {
                    store.update(
                        'teampassUser',
                        function(teampassUser) {
                            teampassUser.uploadFileObject = restoreOperationId;
                        }
                    );
                    
                    $('#onthefly-restore-file-text').text(up.files[0].name);

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                },
                Error: function(up, args) {
                    console.log("ERROR arguments:");
                    console.log(args);
                }
            }
        });

    // Uploader options
    uploader_restoreDB.bind('FileUploaded', function(upldr, file, object) {
        var myData = prepareExchangedData(object.response, "decode", "<?php echo $session->get('key'); ?>");
        $('#onthefly-restore-file').data('operation-id', myData.operation_id);
    });

    uploader_restoreDB.bind("Error", function(up, err) {
        //var myData = prepareExchangedData(err, "decode", "<?php echo $session->get('key'); ?>");
        $("#onthefly-restore-progress")
            .removeClass('hidden')
            .html('<div class="alert alert-danger alert-dismissible ml-2">' +
                '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                '<h5><i class="icon fas fa-ban mr-2"></i><?php echo $lang->get('done'); ?></h5>' +
                '' + err.message +
                '</div>');
                up.refresh(); // Reposition Flash/Silverlight
    });

    uploader_restoreDB.init();

    //]]>
</script>
