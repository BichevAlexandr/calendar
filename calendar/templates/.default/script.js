function notification(type, $msg, title = '')
{
  BX.ready(function(){
    new ToastNotification(
      type,
      title,
      $msg,
      null,
      5000,
    ).show();
  });
}
