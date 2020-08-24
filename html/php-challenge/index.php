<?php
session_start();
require('dbconnect.php');

function redirect()
{
    header('Location: index.php');
    exit();
}
if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
    // ログインしている
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
    // ログインしてるアカウントがいいねしたツイートを探す
    $my_likes = $db->prepare('SELECT post_id FROM post_fav WHERE user_id=?');
    $my_likes->execute(array($_SESSION['id']));
    while ($like = $my_likes->fetch()) {
        $my_like[] = $like;
    };

    // ログインしてるアカウントがRTしたツイートを探す
    $rts = $db->prepare('SELECT rt_post_id FROM posts WHERE rt_user_id=?');
    $rts->execute(array($_SESSION['id']));
    while ($rt = $rts->fetch()) {
        $my_rt[] = $rt;
    };
} else {
    // ログインしていない
    header('Location: login.php');
    exit();
}

// 投稿を記録する
if (!empty($_POST)) {
    if ($_POST['message'] != '') {
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
        $message->execute(array(
            $member['id'],
            $_POST['message'],
            $_POST['reply_post_id']
        ));

        redirect();
    }
}

// 投稿を取得する
$page = 0;
if (isset($_REQUEST['page'])) {
    $page = $_REQUEST['page'];
}
if ($page == '') {
    $page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// いいねの数の取得
$dup_likes = $db->query('SELECT post_id,COUNT(post_id) AS like_cnt FROM post_fav GROUP BY post_id HAVING COUNT(post_id)');
while ($like = $dup_likes->fetch()) {
    $dup_like[] = $like;
};
// RTの数の取得
$dup_rts = $db->query('SELECT members.name as rt_name,rt_post_id,COUNT(rt_post_id) AS rt_cnt FROM posts,members WHERE members.id=posts.rt_user_id GROUP BY rt_post_id HAVING COUNT(rt_post_id) AND rt_post_id>0');
while ($rts = $dup_rts->fetch()) {
    $dup_rt[] = $rts;
}
// 返信の場合
if (isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response->execute(array($_REQUEST['res']));

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}

// RTを押した場合
if (isset($_REQUEST['rt'])) {
    // その投稿にRTしていたかどうか
    // idからそのツイートの全てのデータ抽出
    $rt_infomations = $db->prepare('SELECT * FROM posts WHERE id=?');
    $rt_infomations->execute(array(
        $_REQUEST['rt']
    ));
    $rt_infomation = $rt_infomations->fetch();
    // メッセージと発言した人が同じかで抽出したデータからrt_user_idがログインしてるidと一致するものがあるか
    $rt_pressed = $db->prepare('SELECT posts.*,COUNT(*) AS cnt FROM posts WHERE message=? AND rt_user_id=? AND member_id=?');
    $rt_pressed->execute(array(
        $rt_infomation['message'],
        $_SESSION['id'],
        $rt_infomation['member_id']
    ));
    $my_rt_cnt = $rt_pressed->fetch();

    if ($my_rt_cnt['cnt'] < 1) {
        // RTしていない場合
        $rt_push = $db->prepare('INSERT INTO posts SET member_id=?, message=?,reply_post_id=0,rt_post_id=?,rt_user_id=?,created=NOW()');
        if ((int)$rt_infomation['rt_post_id'] === 0) {
            // オリジナルツイートの場合
            $rt_push->execute(array(
                $rt_infomation['member_id'],
                $rt_infomation['message'],
                $_REQUEST['rt'],
                $_SESSION['id']
            ));
            redirect();
        } else {
            // RTされたものをRTした場合
            $rt_push->execute(array(
                $rt_infomation['member_id'],
                $rt_infomation['message'],
                $rt_infomation['rt_post_id'],
                $_SESSION['id']
            ));
            redirect();
        }
    } else {
        // RTしていた場合
        if ((int)$rt_infomation['rt_post_id'] === 0) {
            // オリジナルの場合rt先のデータを削除
            $rt_cancel = $db->prepare('DELETE FROM posts WHERE rt_post_id=? AND rt_user_id=?');
            $rt_cancel->execute(array($_REQUEST['rt'], $_SESSION['id']));

            redirect();
        } else {
            // RTされたツイートである場合そのデータを削除
            $rt_cancel = $db->prepare('DELETE FROM posts WHERE rt_post_id=? AND rt_user_id=?');
            $rt_cancel->execute(array($my_rt_cnt['rt_post_id'], $_SESSION['id']));

            redirect();
        }
    }
}
// ❤を押した場合
if (isset($_REQUEST['like'])) {
    // idからrt元のデータがあるか抽出
    $rt_post_ids = $db->prepare('SELECT rt_post_id FROM posts WHERE id=?');
    $rt_post_ids->execute(array(
        $_REQUEST['like']
    ));
    $rt_post_id = $rt_post_ids->fetch();
    // いいねされていたか
    $fav_pressed = $db->prepare('SELECT COUNT(*) AS cnt FROM post_fav WHERE post_id=? AND user_id=?');
    // オリジナルの時
    if ((int)$rt_post_id['rt_post_id'] === 0) {
        $fav_pressed->execute(array(
            $_REQUEST['like'],
            $_SESSION['id']
        ));
    } else {
        // RTされたツイートの場合
        $fav_pressed->execute(array(
            $rt_post_id['rt_post_id'],
            $_SESSION['id']
        ));
    }

    $my_fav_cnt = $fav_pressed->fetch();
    if ($my_fav_cnt['cnt'] < 1) {
        // いいねしていない場合
        $fav_push = $db->prepare('INSERT INTO post_fav SET post_id=?,user_id=?');
        if ((int)$rt_post_id['rt_post_id'] === 0) {
            // オリジナルの場合
            $fav_push->execute(array($_REQUEST['like'], $_SESSION['id']));
            redirect();
        } else {
            // RTされたツイートの場合
            $fav_push->execute(array($rt_post_id['rt_post_id'], $_SESSION['id']));
            redirect();
        }
    } else {
        // いいねしていた場合
        $fav_cancel = $db->prepare('DELETE FROM post_fav WHERE post_id=? AND user_id=?');
        if ((int)$rt_post_id['rt_post_id'] === 0) {
            // オリジナルの場合
            $fav_cancel->execute(array($_REQUEST['like'], $_SESSION['id']));
            redirect();
        } else {
            // RTされたツイートの場合
            $fav_cancel->execute(array($rt_post_id['rt_post_id'], $_SESSION['id']));
            redirect();
        }
    }
}

// htmlspecialcharsのショートカット
function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value)
{
    return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ひとこと掲示板</title>

    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <div id="wrap">
        <div id="head">
            <h1>ひとこと掲示板</h1>
        </div>
        <div id="content">
            <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
            <form action="" method="post">
                <dl>
                    <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
                    <dd>
                        <textarea name="message" cols="50" rows="5"><?php if (!empty($_POST)) {
                                                                        echo h($message);
                                                                    } ?></textarea>
                        <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
                        <input type="hidden" name="retweet_id" value="<?php echo h($_REQUEST['rt']); ?>">
                    </dd>
                </dl>
                <div>
                    <p>
                        <input type="submit" value="投稿する" />
                    </p>
                </div>
            </form>

            <?php foreach ($posts as $post) :?>
                <div class="msg">
                    <p class="day">
                        <?php
                        if ((int)$post['rt_post_id'] !== 0) {
                            foreach ($dup_rt as $rt) {
                                if ($rt['rt_post_id'] === $post['rt_post_id'] || $rt['rt_post_id'] === $post['id']) {
                                    echo h($rt['rt_name']) . 'さんがRTしました';
                                }
                            }
                        };
                        ?>
                    </p>
                    <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                    <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>
                    <p class="day"><a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
                        <?php if ($post['reply_post_id'] > 0) : ?>
                            <a href="view.php?id=<?php echo
                                                        h($post['reply_post_id']); ?>">
                                返信元のメッセージ</a>
                        <?php endif; ?>
                        <?php
                        $my_fav_cnt = 0;
                        if (!empty($my_like)) {
                            foreach ($my_like as $like_post) {
                                if ($like_post['post_id'] === $post['id'] || $like_post['post_id'] === $post['rt_post_id']) {
                                    $my_fav_cnt = 1;
                                }
                            }
                        }
                        $my_rt_cnt = 0;
                        if (!empty($my_rt)) {
                            foreach ($my_rt as $rt) {
                                if ($rt['rt_post_id'] === $post['rt_post_id'] || $rt['rt_post_id'] === $post['id']) {
                                    $my_rt_cnt = 1;
                                }
                            }
                        }
                        ?>
                        <?php if ($my_rt_cnt < 1) : ?>
                            <a style="color: gray;" href="index.php?rt=<?php echo h($post['id']); ?>">RT</a>
                        <?php else : ?>
                            <a style="color: #00e676;" href="index.php?rt=<?php echo h($post['id']); ?>">RT</a>
                        <?php endif;
                        if (!empty($dup_rt)) {
                            foreach ($dup_rt as $rt) {
                                if ($rt['rt_post_id'] === $post['rt_post_id'] || $rt['rt_post_id'] === $post['id']) {
                                    echo h($rt['rt_cnt']);
                                }
                            }
                        }
                        ?>
                        <?php if ($my_fav_cnt < 1) : ?>
                            <a style="color: gray;" href="index.php?like=<?php echo h($post['id']); ?>">♥</a>
                        <?php else : ?>
                            <a style="color: red;" href="index.php?like=<?php echo h($post['id']); ?>">♥</a>
                        <?php endif;
                        if (!empty($dup_like)) {
                            foreach ($dup_like as $like) {
                                if ($like['post_id'] === $post['id'] || $like['post_id'] === $post['rt_post_id']) {
                                    echo h($like['like_cnt']);
                                }
                            }
                        }
                        ?>

                        <?php if ($_SESSION['id'] == $post['member_id']) : ?>
                            [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color: #F33;">削除</a>]
                        <?php endif; ?>
                    </p>
                </div>
            <?php endforeach; ?>
            <ul class="paging">
                <?php if ($page > 1) { ?>
                    <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
                <?php } else { ?>
                    <li>前のページへ</li>
                <?php } ?>
                <?php if ($page < $maxPage) { ?>
                    <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
                <?php } else { ?>
                    <li>次のページへ</li>
                <?php } ?>
            </ul>
        </div>
    </div>
</body>

</html>
