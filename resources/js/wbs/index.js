import { bindChart } from './chart';
import { bindSelection } from './selection';
import { bindDateControls } from './date-controls';
import { bindProgressDrag } from './progress-drag';
import { bindContextMenu } from './context-menu';
import { bindRealtime } from './realtime';
export function initWbs(){bindChart();bindSelection();bindDateControls();bindProgressDrag();bindContextMenu();bindRealtime();}
