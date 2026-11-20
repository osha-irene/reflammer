# bbs_merlin_dice 구현 지침

- 공통 모듈 규칙은 [`../../docs/module/AGENTS.md`](../../docs/module/AGENTS.md)를 따른다.
- `bbs_dice`는 이미지 렌더와 면 설정 재사용을 위해 의존성으로 유지한다.
- 결과는 `wr_merlin_dice`에 1/2 값의 콤마 문자열, 선택 이름은 `wr_merlin_stat`에 저장한다.
- 폼 체크는 `bbs_custom_action`이 아닌 부가 컬럼 훅 `bbs_customer_sql`에서 처리한다.
- 캐릭터 스탯은 배분 수치(`get_status()`의 `has`)를 그대로 사용한다. 0은 최소값으로 대체하지 않으며, 굴림 수가 0이면 주사위를 굴리지 않고 안내 문구를 표시한다.
- 글과 댓글 각각의 기존 행에 결과가 있으면 다시 굴리지 않는다. “한 번”은 글·댓글 항목별 1회를 뜻한다.
- 원본 MMB 스킨 및 `reflammer_wiki`는 수정하지 않는다.
