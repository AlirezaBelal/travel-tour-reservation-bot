<?php
// Run only in the explicitly invoked maintenance container with a DB admin account.
require __DIR__.'/runtime.php';
if(!in_array('--confirm',$argv,true)){fwrite(STDERR,"Explicit --confirm required.\n");exit(1);}
$user=$env('RUNTIME_DB_USERNAME');$database=$env('DB_DATABASE');
if(strtolower($user)==='root'||!preg_match('/^[A-Za-z0-9_]{1,32}$/D',$user)||!preg_match('/^[A-Za-z0-9_]+$/D',$database)){
    fwrite(STDERR,"A dedicated alphanumeric runtime database user is required.\n");exit(1);
}
try{
    // Account/identifier strings are strictly allowlisted before DCL interpolation.
    // MySQL DCL commits implicitly; rerunning repairs a partial revoke/grant.
    $pdo->exec("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '$user'@'%'");
    $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `$database`.* TO '$user'@'%'");
    echo"Runtime user restricted to application DML.\n";
}catch(Throwable){fwrite(STDERR,"Runtime privilege update failed; review maintenance access privately and rerun before enabling traffic.\n");exit(1);}
