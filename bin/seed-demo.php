<?php
require __DIR__.'/runtime.php';
if(!in_array($env('APP_ENV'),['development','test'],true)||!in_array('--confirm',$argv,true)){
    fwrite(STDERR,"Demo seeding requires APP_ENV=development|test and --confirm.\n");exit(1);
}
if((int)$pdo->query("SELECT GET_LOCK('travel_tour_demo_seed',10)")->fetchColumn()!==1)exit(1);
try{
    $id=Database::atomic($pdo,function()use($pdo,$bookings,$owners){
        $existing=Database::run($pdo,'SELECT id FROM Tours WHERE title=?',['تور نمایشی — داده ساختگی'])->fetchColumn();
        if($existing!==false)return(int)$existing;
        $category=(new TopicCatalogue($pdo))->create('دسته نمایشی — داده ساختگی',0);
        return $bookings->saveTour($owners[0],['category_id'=>$category,'title'=>'تور نمایشی — داده ساختگی','destination'=>'مقصد نمایشی','description'=>'این تور و همه مشخصات آن فقط برای آزمایش نصب است. پیش‌نویس منتشر نمی‌شود.','starts_at'=>gmdate('Y-m-d H:i:s',time()+7*86400),'ends_at'=>gmdate('Y-m-d H:i:s',time()+7*86400+7200),'price'=>0,'capacity'=>8,'publication'=>'draft']);
    });
    fwrite(STDOUT,"Synthetic draft tour: $id\n");
}finally{$pdo->query("SELECT RELEASE_LOCK('travel_tour_demo_seed')");}
