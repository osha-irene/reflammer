# bbs_duel 구현 지침

- 공통 규칙은 [`../../docs/module/AGENTS.md`](../../docs/module/AGENTS.md)를 따른다.
- 에너미는 character/NPC 레코드가 아니라 `module_bbs_duel_enemy`의 독립 엔티티다.
- 플레이어 대상은 승인된 `main` 또는 `sub` 캐릭터이며, 작성 캐릭터 자신은 제외한다.
- ACTION의 `DUEL_SPAR` / `DUEL` 토큰을 사용하고 `action_<token>` 패널 ID 규칙을 지킨다.
- 멀린의 주사위 helper를 사용한다. 동일 판정 스탯으로 양쪽을 각각 굴린다.
- 판정은 지원되는 스탯 1개 또는 2개 조합이며, 조합은 개별 스탯 수치의 합만큼 D2를 굴린다. 에너미의 허용 조합은 de_allowed_stats로 제한하고 빈 값은 전체 허용이다.
- 현재 버전은 판정·기록까지 다룬다. HP·스킬 효과는 별도 단계다.
