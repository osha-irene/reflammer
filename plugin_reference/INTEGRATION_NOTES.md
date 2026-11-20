# reflammer 플러그인 통합 메모

## 프로젝트 맥락

- reflammer는 해리포터 세계관을 바탕으로 한 비영리 자캐 커뮤니티다. 예상 참여 인원은 7명, 최대 10명 이하.
- 회원/캐릭터 프로필, 자유 창작 게시판(자비란), 로그 게시판을 기본으로 하고, 스테이터스·경험치·아이템·스킬·배틀 등 선택형 성장 기능을 사용하려 한다.
- 기능별로 새 채팅을 열어 작업하는 방식을 선호한다. 새 채팅은 같은 reflammer 작업공간에서 시작하고 이 문서를 먼저 참고한다.
- 보안 이슈는 사용자가 가장 나중에 작업하기로 했다. 플러그인 통합 검토 중에는 별도 요청 없이 보안 수정으로 범위를 바꾸지 않는다.
- 기존 파일을 무작정 덮어쓰지 말고, 현재 Avocado Edition 8 코드와 충돌을 확인한 뒤 필요한 부분만 병합한다.

## 현재 검토 상태

- ZIP은 `plugin_reference/`에 원본 그대로 보관되어 있다. 1차 검토는 압축을 풀거나 코드에 적용하지 않고 내부 파일 목록과 설치 문서를 읽는 방식으로 했다.
- 현재 포커스는 **MyRoom 플러그인부터 시험적으로 통합**하는 것이다.
- 저장소는 Avocado Edition 8을 표기하지만 일부 플러그인 문서는 Avocado Edition 2.2.2 기준이므로 버전/API 호환성을 확인해야 한다.

## Battle / SkillManager / StatusExtra 상담 결정 (2026-11-20)

- 기존 Battle 플러그인은 Avocado Edition 2.2.2 기반이며 StatusExtra 의존 및 MMB 스킨 수정이 있어 그대로 설치하지 않는다. 전투 흐름만 참고한다.
- 현재 전투 기능은 독립 모듈 `bbs_duel`로 단계 구현한다. 대련/결투는 MMB ACTION 메뉴에서 선택하고, 에디션 8의 모듈 훅과 현재 멀린의 주사위를 활용한다.
- 에너미는 NPC 캐릭터와 별개인 관리 엔티티로 운영한다. `bbs_duel`은 선택한 공통 스탯으로 양쪽의 멀린 D2를 자동 굴려 결과를 기록한다. HP·스킬 처리는 후속 단계다.
- StatusExtra의 복잡한 수식 연동과 SkillManager의 광범위한 코어/DB 확장은 현재 설계에서 채택하지 않는다.

## 플러그인 목록과 의존성

| ZIP | 기능 요약 | 참고 |
|---|---|---|
| `AvocadoEdition.MyRoom.20211014.zip` | 캐릭터별 방 꾸미기. 배경·가구·커스텀 가구 아이템을 인벤토리에서 사용 | 첫 통합 대상으로 선택됨. README는 구매 파일이라고 안내하며 재배포를 금지함 |
| `AvocadoEdition.StatusExtra.zip` | 캐릭터 스탯을 전투용 연동 코드/계산에 연결 | Battle의 필수 의존성. 현재 스테이터스 관리 파일과 충돌 확인 필요. README는 구매 파일이라고 안내 |
| `AvocadoEdition.SkillManager.240429.zip` | 스킬 관리, 보유 스킬과 성장/사용 화면 | Dungeon Manager가 스킬 테이블·함수를 참조 |
| `AvocadoEdition.Battle.20240213.zip` | MMB 게시판의 1:1 전투와 스탯 기반 결과 기록 | StatusExtra 필요. MMB 스킨 수정/추가 포함. StatusExtra와 `adm/admin.menu910.php`를 공유 |
| `AvocadoEdition.DungeonManager.241205.zip` | 던전 참가·몬스터 전투·HP·스킬/아이템 사용·진행 기록 | 코드상 StatusExtra, SkillManager, FullMapManager의 맵 테이블/필드에 의존. 코어 아이템 관리·마이페이지 로그 파일과 충돌 |
| `AvocadoEdition.FullMapManager.240212.zip` | 지역 지도, 이동, 이벤트 | Dungeon Manager가 요구하는 맵 구조. 현재 내장 `bbs_action_map`과는 별도 시스템 |
| `AvocadoEdition_RandomDice-master.zip` | MMB 전용 랜덤 주사위/결과와 전용 스킨 | 현재 내장 주사위 모듈과 기능이 겹칠 수 있고 MMB 스킨 패치가 필요 |

Battle·StatusExtra·MyRoom 안내문은 구매/재배포 제한을 명시한다. 라이선스 조건을 지키고 공개 저장소에 원본 소스를 재배포하지 않는다.

## MyRoom 첫 작업 범위

1. `AvocadoEdition.MyRoom.20211014.zip`의 README와 PHP 파일을 읽고 현재 에디션 8과 호환되는지 확인한다.
2. 기존 `AvocadoEdition/` 파일과 경로 충돌, 아이템/인벤토리 확장 방식, DB 변경 여부를 확인한다.
3. 현재 코어 및 테마와 충돌하지 않는 통합 계획을 먼저 제시한다.
4. 계획에 맞춰 필요한 파일만 적용하고, 설치/사용 절차를 기록한다. 원본 ZIP은 보존한다.

