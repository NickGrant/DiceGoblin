from __future__ import annotations

from collections import deque
from pathlib import Path

import numpy as np
from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
UI_SHEETS_DIR = ROOT / 'raw-assets' / 'ui-sheets'


SECTION_CONFIG = {
    '1': {
        'source': UI_SHEETS_DIR / '1.png',
        'sections': [
            {'name': 'panel_frames', 'box': (8, 86, 898, 327), 'min_area': 700},
            {'name': 'nine_slice_parts', 'box': (908, 86, 1664, 327), 'min_area': 140},
            {'name': 'buttons', 'box': (8, 347, 438, 619), 'min_area': 160},
            {'name': 'resource_chips', 'box': (448, 347, 759, 619), 'min_area': 120},
            {'name': 'navigation_cards', 'box': (769, 347, 1254, 619), 'min_area': 220},
            {'name': 'slots', 'box': (1264, 347, 1664, 619), 'min_area': 120},
            {'name': 'controls', 'box': (8, 641, 438, 932), 'min_area': 120},
            {'name': 'badges', 'box': (448, 641, 758, 932), 'min_area': 120},
            {'name': 'decor', 'box': (768, 641, 1256, 932), 'min_area': 70},
            {'name': 'header_pieces', 'box': (1266, 641, 1664, 932), 'min_area': 120},
        ],
    },
    '2': {
        'source': UI_SHEETS_DIR / '2.png',
        'background': 'dark',
        'sections': [
            {'name': 'textures', 'box': (8, 86, 1036, 223), 'min_area': 1800},
            {'name': 'patterns', 'box': (1050, 86, 1664, 223), 'min_area': 900},
            {'name': 'backplates', 'box': (8, 259, 1664, 365), 'min_area': 1400},
            {'name': 'content_mats', 'box': (8, 402, 934, 536), 'min_area': 900},
            {'name': 'dividers', 'box': (944, 402, 1278, 536), 'min_area': 80},
            {'name': 'attachments', 'box': (1288, 402, 1664, 536), 'min_area': 120},
            {'name': 'damage_decals', 'box': (8, 572, 708, 688), 'min_area': 45},
            {'name': 'state_overlays', 'box': (718, 572, 1664, 688), 'min_area': 700},
            {'name': 'slot_interiors', 'box': (8, 721, 982, 799), 'min_area': 160},
            {'name': 'micro_tags', 'box': (992, 721, 1664, 799), 'min_area': 80},
            {'name': 'loading', 'box': (8, 837, 982, 932), 'min_area': 120},
            {'name': 'mobile_blanks', 'box': (992, 837, 1664, 932), 'min_area': 120},
        ],
    },
    '3': {
        'source': UI_SHEETS_DIR / '3.png',
        'background': 'light',
        'sections': [
            {'name': 'textures', 'box': (8, 86, 982, 235), 'min_area': 1600},
            {'name': 'patterns', 'box': (994, 86, 1664, 235), 'min_area': 700},
            {'name': 'backplates', 'box': (8, 258, 1664, 370), 'min_area': 1200},
            {'name': 'content_mats', 'box': (8, 404, 838, 564), 'min_area': 900},
            {'name': 'dividers', 'box': (846, 404, 1263, 564), 'min_area': 100},
            {'name': 'attachments', 'box': (1266, 404, 1664, 564), 'min_area': 120},
            {'name': 'wear_decals', 'box': (8, 593, 832, 758), 'min_area': 45},
            {'name': 'state_overlays', 'box': (842, 593, 1261, 758), 'min_area': 700},
            {'name': 'slot_interiors', 'box': (1264, 593, 1460, 758), 'min_area': 250},
            {'name': 'micro_tags', 'box': (1466, 593, 1664, 758), 'min_area': 100},
            {'name': 'loading', 'box': (8, 790, 862, 932), 'min_area': 100},
            {'name': 'mobile_blanks', 'box': (870, 790, 1664, 932), 'min_area': 120},
        ],
    },
    '4': {
        'source': UI_SHEETS_DIR / '4.png',
        'background': 'light',
        'sections': [
            {'name': 'panel_frames', 'box': (8, 86, 900, 330), 'min_area': 700},
            {'name': 'nine_slice_parts', 'box': (908, 86, 1664, 330), 'min_area': 140},
            {'name': 'buttons', 'box': (8, 349, 438, 620), 'min_area': 160},
            {'name': 'resource_chips', 'box': (448, 349, 759, 620), 'min_area': 120},
            {'name': 'navigation_cards', 'box': (769, 349, 1254, 620), 'min_area': 220},
            {'name': 'slots', 'box': (1264, 349, 1664, 620), 'min_area': 120},
            {'name': 'controls', 'box': (8, 641, 438, 932), 'min_area': 120},
            {'name': 'badges', 'box': (448, 641, 758, 932), 'min_area': 120},
            {'name': 'decor', 'box': (768, 641, 1256, 932), 'min_area': 70},
            {'name': 'header_pieces', 'box': (1266, 641, 1664, 932), 'min_area': 120},
        ],
    },
    '5': {
        'source': UI_SHEETS_DIR / '5.png',
        'background': 'light',
        'sections': [
            {'name': 'panel_frames', 'box': (8, 86, 898, 303), 'min_area': 700},
            {'name': 'nine_slice_parts', 'box': (900, 86, 1664, 303), 'min_area': 140},
            {'name': 'buttons', 'box': (8, 324, 438, 589), 'min_area': 160},
            {'name': 'resource_chips', 'box': (448, 324, 759, 589), 'min_area': 120},
            {'name': 'navigation_cards', 'box': (769, 324, 1254, 589), 'min_area': 220},
            {'name': 'slots', 'box': (1264, 324, 1664, 589), 'min_area': 120},
            {'name': 'controls', 'box': (8, 601, 438, 885), 'min_area': 120},
            {'name': 'badges', 'box': (448, 601, 758, 885), 'min_area': 120},
            {'name': 'decor', 'box': (768, 601, 1256, 885), 'min_area': 70},
            {'name': 'header_pieces', 'box': (1266, 601, 1664, 885), 'min_area': 120},
            {'name': 'large_swatches', 'box': (8, 885, 1664, 940), 'min_area': 500},
        ],
    },
    '6': {
        'source': UI_SHEETS_DIR / '6.png',
        'background': 'light',
        'sections': [
            {'name': 'large_textures', 'box': (8, 70, 1664, 233), 'min_area': 1600},
            {'name': 'patterns', 'box': (8, 248, 734, 342), 'min_area': 700},
            {'name': 'backplates', 'box': (748, 248, 1664, 477), 'min_area': 1200},
            {'name': 'content_mats', 'box': (8, 361, 672, 485), 'min_area': 900},
            {'name': 'dividers', 'box': (8, 505, 665, 655), 'min_area': 100},
            {'name': 'attachments', 'box': (677, 505, 1180, 655), 'min_area': 120},
            {'name': 'wear_decals', 'box': (1187, 505, 1664, 655), 'min_area': 45},
            {'name': 'state_overlays', 'box': (8, 682, 536, 822), 'min_area': 700},
            {'name': 'slot_interiors', 'box': (546, 682, 1179, 822), 'min_area': 250},
            {'name': 'micro_tags', 'box': (1187, 682, 1664, 822), 'min_area': 100},
            {'name': 'loading', 'box': (8, 840, 841, 940), 'min_area': 100},
            {'name': 'mobile_blanks', 'box': (849, 840, 1664, 940), 'min_area': 120},
        ],
    },
}


def dark_background_candidate_mask(rgb: np.ndarray) -> np.ndarray:
    max_channel = rgb.max(axis=2)
    mean_channel = rgb.mean(axis=2)
    min_channel = rgb.min(axis=2)
    return (max_channel < 28) & (mean_channel < 18) & ((max_channel - min_channel) < 14)


def light_background_candidate_mask(rgb: np.ndarray) -> np.ndarray:
    min_channel = rgb.min(axis=2)
    mean_channel = rgb.mean(axis=2)
    max_channel = rgb.max(axis=2)
    return (min_channel > 150) & (mean_channel > 178) & ((max_channel - min_channel) < 75)


def flood_fill_border(mask: np.ndarray) -> np.ndarray:
    height, width = mask.shape
    visited = np.zeros((height, width), dtype=bool)
    queue: deque[tuple[int, int]] = deque()

    def try_add(x: int, y: int) -> None:
        if x < 0 or y < 0 or x >= width or y >= height:
            return
        if visited[y, x] or not mask[y, x]:
            return
        visited[y, x] = True
        queue.append((x, y))

    for x in range(width):
        try_add(x, 0)
        try_add(x, height - 1)
    for y in range(height):
        try_add(0, y)
        try_add(width - 1, y)

    while queue:
        x, y = queue.popleft()
        for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            try_add(x + dx, y + dy)

    return visited


def foreground_mask(rgb: np.ndarray, background_mode: str) -> np.ndarray:
    if background_mode == 'light':
        bg_candidates = light_background_candidate_mask(rgb)
    else:
        bg_candidates = dark_background_candidate_mask(rgb)
    border_background = flood_fill_border(bg_candidates)
    return ~border_background


def connected_components(mask: np.ndarray, min_area: int) -> list[tuple[int, int, int, int]]:
    height, width = mask.shape
    visited = np.zeros((height, width), dtype=bool)
    boxes: list[tuple[int, int, int, int]] = []

    for y in range(height):
        for x in range(width):
            if visited[y, x] or not mask[y, x]:
                continue

            queue: deque[tuple[int, int]] = deque([(x, y)])
            visited[y, x] = True
            area = 0
            min_x = max_x = x
            min_y = max_y = y

            while queue:
                cx, cy = queue.popleft()
                area += 1
                min_x = min(min_x, cx)
                max_x = max(max_x, cx)
                min_y = min(min_y, cy)
                max_y = max(max_y, cy)

                for dx in (-1, 0, 1):
                    for dy in (-1, 0, 1):
                        if dx == 0 and dy == 0:
                            continue
                        nx = cx + dx
                        ny = cy + dy
                        if nx < 0 or ny < 0 or nx >= width or ny >= height:
                            continue
                        if visited[ny, nx] or not mask[ny, nx]:
                            continue
                        visited[ny, nx] = True
                        queue.append((nx, ny))

            if area < min_area:
                continue

            boxes.append((min_x, min_y, max_x + 1, max_y + 1))

    boxes.sort(key=lambda box: (box[1] // 24, box[0]))
    return boxes


def crop_to_alpha_bounds(image: Image.Image) -> Image.Image:
    alpha = image.getchannel('A')
    bbox = alpha.getbbox()
    if bbox is None:
      return image
    return image.crop(bbox)


def transparent_piece(image: Image.Image, background_mode: str) -> Image.Image:
    rgba = image.convert('RGBA')
    rgb = np.array(rgba)[:, :, :3]
    mask = foreground_mask(rgb, background_mode)
    alpha = np.where(mask, 255, 0).astype(np.uint8)
    output = np.array(rgba)
    output[:, :, 3] = alpha
    return crop_to_alpha_bounds(Image.fromarray(output, mode='RGBA'))


def save_section_pieces(
    sheet_key: str,
    section: dict[str, object],
    sheet_image: Image.Image,
    background_mode: str,
) -> int:
    box = section['box']
    section_name = str(section['name'])
    min_area = int(section['min_area'])
    region = sheet_image.crop(box)
    region_mask = foreground_mask(np.array(region.convert('RGB')), background_mode)
    boxes = connected_components(region_mask, min_area=min_area)

    output_dir = UI_SHEETS_DIR / sheet_key / section_name
    output_dir.mkdir(parents=True, exist_ok=True)

    for existing_file in output_dir.glob('*.png'):
        existing_file.unlink()

    saved = 0
    for index, component_box in enumerate(boxes, start=1):
        min_x, min_y, max_x, max_y = component_box
        pad = 4
        padded_box = (
            max(0, min_x - pad),
            max(0, min_y - pad),
            min(region.width, max_x + pad),
            min(region.height, max_y + pad),
        )
        piece = transparent_piece(region.crop(padded_box), background_mode)
        if piece.width == 0 or piece.height == 0:
            continue
        piece.save(output_dir / f'{section_name}_{index:02d}.png')
        saved += 1

    return saved


def main() -> None:
    for sheet_key, config in SECTION_CONFIG.items():
        sheet_output_dir = UI_SHEETS_DIR / sheet_key
        sheet_output_dir.mkdir(parents=True, exist_ok=True)
        image = Image.open(config['source']).convert('RGBA')
        background_mode = str(config.get('background', 'dark'))

        saved_counts: list[tuple[str, int]] = []
        for section in config['sections']:
            saved_counts.append((
                str(section['name']),
                save_section_pieces(sheet_key, section, image, background_mode),
            ))

        print(f'Sheet {sheet_key}:')
        for section_name, count in saved_counts:
            print(f'  {section_name}: {count}')


if __name__ == '__main__':
    main()
