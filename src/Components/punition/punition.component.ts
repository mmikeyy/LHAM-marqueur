import { Component, OnInit, OnDestroy, Input } from '@angular/core';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { EditePunitionComponent } from '../../pages/feuilleMatch/editePunition/editePunition.component';
import { FmService } from '../../services/fmService';
import { MatchsService } from '../../services/matchs.service';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: 'fm-punition',
    templateUrl: './punition.component.html'
  }
)
export class PunitionComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];

  @Input() ev;
  @Input() editable;

  constructor(
    public store: NgRedux<IAppState>,
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

  click(op) {
    switch (op) {
      case 'edit':
        this.navCtrl.push(EditePunitionComponent, {ev: this.ev.toJS()});
        return;
      case 'delete':
        this.fmServ.effacerEv(this.ev);
        return;
      default:

    }
  }

}
