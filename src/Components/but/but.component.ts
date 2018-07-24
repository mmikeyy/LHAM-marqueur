import { Component, EventEmitter, OnInit, OnDestroy, Input } from '@angular/core';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { TranslateService } from '@ngx-translate/core';
import { NgRedux, select } from '@angular-redux/store';
import { EditeButComponent } from '../../pages/feuilleMatch/editeBut/editeBut.component';
import { FmService } from '../../services/fmService';
import { MatchsService } from '../../services/matchs.service';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: 'fm-but',
    templateUrl: './but.component.html'
  }
)
export class ButComponent implements OnInit, OnDestroy {

  @Input() ev;
  @Input() editable;

  public subs: Subscription[] = [];
  public R = R;

  @select(['pratiques', 'ressources']) $ressources;

  constructor(
    public store: NgRedux<IAppState>,
    public trServ: TranslateService,
    public navCtrl: NavController,
    public matchsServ: MatchsService,
    public fmServ: FmService
  ) {

  }

  ngOnInit() {
    this.subs.push();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  tr(tag, data = {}) {
    return this.trServ.instant(tag, data);
  }

  click(op) {
    this.fmServ.closeSlidingEvMenu.emit();
    switch(op) {
      case 'edit':
        this.navCtrl.push(EditeButComponent, {
          ev: this.ev.toJS()
        });
        return;
      case 'delete':
        this.fmServ.effacerEv(this.ev);
        return;
      default:
    }
  }

  score() {
    return R.pipe(
      R.sortBy(R.negate),
      R.join(' - ')
    )([this.ev.get('resultat'), this.ev.get('resultat_adversaire')]);
  }

  nomEquipeEnAvance() {
    let res = this.ev.get('resultat');
    let res_adv = this.ev.get('resultat_adversaire');
    if (res === res_adv) {
      return '';
    }
    return  this.fmServ[res > res_adv ? 'nomEquipe' : 'nomAutreEquipe'](this.ev.get('id_equipe'))
  }
}
