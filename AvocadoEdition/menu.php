<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 테마 menu.php 있으면 해당 파일에서 처리
if (function_exists('theme_include') && ($_p = theme_include('menu', 'menu.php'))) { include $_p; return; }

// cf_menu_text / cf_mobile_menu_text 가 설정되어 있으면 우선 출력
$_menu_html = '';
if (defined('G5_IS_MOBILE') && G5_IS_MOBILE && !empty($config['cf_mobile_menu_text'])) {
    $_menu_html = $config['cf_mobile_menu_text'];
} elseif (!empty($config['cf_menu_text'])) {
    $_menu_html = $config['cf_menu_text'];
}
if ($_menu_html !== '') {
    echo $_menu_html;
    return;
}

// "화면 설정 → 메뉴영역"에서 직접 입력한 내용이 있으면, 전체를 대체하지 않고
// #gnb 안쪽(<ul id="no_design_gnb"> 내부)만 바꿔 끼운다 — 로고·금색 프레임
// 디자인은 그대로 유지한 채 관리자가 링크만 자유롭게 편집할 수 있게 하기 위함.
// 에디터는 보통 <li> 없이 <a> 태그만 남기므로, 찾아낸 <a> 하나당 <li>로 감싸준다.
$menu_content = function_exists('get_site_content') ? get_site_content('site_menu') : '';
$menu_items = '';
if ($menu_content && preg_match_all('/<a\b[^>]*>.*?<\/a>/is', $menu_content, $_menu_m)) {
    foreach ($_menu_m[0] as $_menu_a) $menu_items .= '<li>'.$_menu_a.'</li>';
}
?>
<header id="header">
    <div class="fix-layout">
        <!-- 로고 영역 -->
        <h1 id="logo">
            <a href="<?php echo G5_URL; ?>/main.php">
                <?php echo $logo_data; ?>
            </a>
        </h1>

        <!-- 모바일 메뉴 열고 닫기 버튼 -->
        <a href="#gnb" id="gnb_control_box">
            <img src="<?php echo G5_IMG_URL; ?>/ico_menu_control_pannel.png" alt="메뉴열고닫기">
        </a>
        <script>
        (function($){
            $('#gnb_control_box').on('click', function(){
                $('body').toggleClass('open-gnb');
                return false;
            });
        })(jQuery);
        </script>
        <!-- // 모바일 메뉴 열고 닫기 버튼 -->

        <div id="gnb">
           <ul id="no_design_gnb">
<?php if ($menu_items !== '') {
    echo $menu_items;
} else { ?>
                <li><a href="<?php echo G5_URL; ?>/notice">Notice</a></li>
                <li><a href="<?php echo G5_URL; ?>/world">World</a></li>
                <li><a href="<?php echo G5_URL; ?>/member">Members</a></li>
                <li><a href="<?php echo G5_URL; ?>/member/ready.php">Applicants</a></li>
                <li><a href="<?php echo G5_URL; ?>/bbs/board.php?bo_table=test">Test</a></li>
                <li><a href="<?php echo G5_URL; ?>/timeline">Timeline</a></li>
                <li><a href="<?php echo G5_URL; ?>/shop">Shop</a></li>
                <li><a href="<?php echo G5_URL; ?>/mypage">My Page</a></li>
                <li><a href="<?php echo G5_URL; ?>/module/reflammer_wiki/index.php">Wiki</a></li>
<?php } ?>
            </ul>
            <?php include G5_PATH.'/templete/txt.bgm.php'; ?>
        </div>
    </div>
</header>

