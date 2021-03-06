module.service('ASPADataService',[function(){
    var data = {};
    if(localStorage && !_.isEmpty(localStorage['AliceSPA_Data'])){
        data = JSON.parse(localStorage['AliceSPA_Data']);
        data = data || {};
    }
    return {
            set:function(key,value){
                data[key] = value;
                if(localStorage){
                    localStorage['AliceSPA_Data'] = JSON.stringify(data);
                }
            },
            get:function(key){return data[key];},
            getAll: function(){return data;}
        }
    }
]);
