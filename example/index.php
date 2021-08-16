<?php
require_once("config.php");
require_once("../regapi/regapi.php");

$ra = new RegAPI($caps);
?><!DOCTYPE html>
<html>
    <head>
        <title>Example registration page</title>
    </head>
    <body>
        <h1>Welcome to example.edu!</h1>
        Welcome Student! Please create an account below:
        <fieldset>
            <form method="post" action="/example/register.php">
                <label>Username: 
                    <input name="username" autocomplete="name" value="<?php echo generateUniqueName(); ?>" size="32">
                </label><br/>
                <label>Starting Avatar: 
                    <select name="avatar">
                        <?php
                            foreach($ra->getAvatars() as $key => $value){
                                echo "<option value=\"".$key."\">".$value."</option>";
                            }
                        ?>
                    </select>
                </label><br/>
                <label>Receive emails: 
                    <input type="checkbox" name="marketing" />
                </label><br/>
                Are you an adult?:<br/>
                &nbsp;&nbsp;&nbsp;&nbsp;<label>No: <input type="radio" name="maturity" value="General" checked="true"></label><br/>
                &nbsp;&nbsp;&nbsp;&nbsp;<label>Yes: <input type="radio" name="maturity" value="Adult"></label><br/>
                <br/>
                <input type="submit" />
            </form>
        </fieldset>
    </body>
</html>